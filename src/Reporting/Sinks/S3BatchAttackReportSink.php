<?php

namespace CodeDart\SlideCaptcha\Reporting\Sinks;

use CodeDart\SlideCaptcha\Contracts\AttackReportSink;
use CodeDart\SlideCaptcha\Reporting\AttackReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class S3BatchAttackReportSink implements AttackReportSink
{
    protected $cache;

    protected $storage;

    public function __construct($cache = null, $storage = null)
    {
        $this->cache = $cache;
        $this->storage = $storage;
    }

    public function record(array $report)
    {
        $cache = $this->cache();
        $sequenceKey = $this->key() . ':sequence';

        $cache->add($sequenceKey, 0, $this->ttl());
        $sequence = $cache->increment($sequenceKey);

        if (! is_numeric($sequence)) {
            $sequence = (int) $cache->get($sequenceKey, 0) + 1;
            $cache->put($sequenceKey, $sequence, $this->ttl());
        }

        $cache->put($this->itemKey((int) $sequence), AttackReport::normalize($report), $this->ttl());
        $cache->put($this->key() . ':latest', (int) $sequence, $this->ttl());
    }

    public function pending()
    {
        return array_map(function ($entry) {
            return $entry['report'];
        }, $this->pendingEntries());
    }

    protected function pendingEntries()
    {
        $cache = $this->cache();
        $latest = (int) $cache->get($this->key() . ':latest', 0);
        $flushed = (int) $cache->get($this->key() . ':flushed', 0);
        $entries = [];

        for ($sequence = $flushed + 1; $sequence <= $latest; $sequence++) {
            $report = $cache->get($this->itemKey($sequence));

            if (is_array($report)) {
                $entries[] = [
                    'sequence' => $sequence,
                    'report' => AttackReport::normalize($report),
                ];
            }
        }

        return $entries;
    }

    public function flush()
    {
        $entries = $this->pendingEntries();

        if (count($entries) === 0) {
            return [
                'flushed' => 0,
                'path' => null,
            ];
        }

        $lines = [];

        foreach ($entries as $entry) {
            $lines[] = json_encode($entry['report'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $path = $this->path();

        try {
            $this->storage()->put($path, implode("\n", $lines) . "\n", [
                'visibility' => $this->visibility(),
                'ContentType' => 'text/plain; charset=UTF-8',
            ]);

            foreach ($entries as $entry) {
                $this->cache()->forget($this->itemKey($entry['sequence']));
            }

            $last = $entries[count($entries) - 1]['sequence'];
            $this->cache()->put($this->key() . ':flushed', $last, $this->ttl());
        } catch (Throwable $exception) {
            return [
                'flushed' => 0,
                'path' => null,
                'error' => $exception->getMessage(),
            ];
        }

        return [
            'flushed' => count($entries),
            'path' => $path,
        ];
    }

    protected function cache()
    {
        if ($this->cache) {
            return $this->cache;
        }

        $store = config('captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function storage()
    {
        if ($this->storage) {
            return $this->storage;
        }

        return Storage::disk((string) config(
            'captcha.ddos.reporting.s3_batch.disk',
            config('captcha.storage_disk', 's3')
        ));
    }

    protected function key()
    {
        return (string) config('captcha.ddos.reporting.s3_batch.cache_key', 'slide_captcha_attack_reports:s3_batch');
    }

    protected function itemKey($sequence)
    {
        return $this->key() . ':item:' . (int) $sequence;
    }

    protected function ttl()
    {
        return max(1, (int) config('captcha.ddos.reporting.s3_batch.cache_ttl', 3600));
    }

    protected function visibility()
    {
        return (string) config('captcha.ddos.reporting.s3_batch.visibility', 'private');
    }

    protected function path()
    {
        $template = (string) config(
            'captcha.ddos.reporting.s3_batch.path',
            'slide-captcha/attack-reports/{date}/{datetime}-{uuid}.jsonl'
        );

        $replacements = [
            '{date}' => date('Y-m-d'),
            '{datetime}' => date('Ymd_His'),
            '{uuid}' => $this->id(),
        ];

        return strtr($template, $replacements);
    }

    protected function id()
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable $exception) {
            return str_replace('.', '', uniqid('', true));
        }
    }
}
