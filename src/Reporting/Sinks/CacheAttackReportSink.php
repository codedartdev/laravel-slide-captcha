<?php

namespace CodeDart\SlideCaptcha\Reporting\Sinks;

use CodeDart\SlideCaptcha\Contracts\AttackReportSink;
use CodeDart\SlideCaptcha\Reporting\AttackReport;
use Illuminate\Support\Facades\Cache;

class CacheAttackReportSink implements AttackReportSink
{
    protected $cache;

    public function __construct($cache = null)
    {
        $this->cache = $cache;
    }

    public function record(array $report)
    {
        $cache = $this->cache();
        $key = $this->key();
        $reports = $cache->get($key, []);

        if (! is_array($reports)) {
            $reports = [];
        }

        $reports[] = AttackReport::normalize($report);
        $reports = array_slice($reports, -1 * $this->limit());

        $cache->put($key, $reports, $this->ttl());
    }

    public function reports($limit = null)
    {
        $reports = $this->cache()->get($this->key(), []);

        if (! is_array($reports)) {
            return [];
        }

        $reports = array_map(function ($report) {
            return is_array($report) ? AttackReport::normalize($report) : null;
        }, $reports);

        $reports = array_values(array_filter($reports));

        if ($limit !== null) {
            $reports = array_slice($reports, -1 * max(1, (int) $limit));
        }

        return $reports;
    }

    public function clear()
    {
        $this->cache()->forget($this->key());
    }

    protected function cache()
    {
        if ($this->cache) {
            return $this->cache;
        }

        $store = config('captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    protected function key()
    {
        return (string) config('captcha.ddos.reporting.cache.key', 'slide_captcha_attack_reports:cache');
    }

    protected function ttl()
    {
        return max(1, (int) config('captcha.ddos.reporting.cache.ttl', 3600));
    }

    protected function limit()
    {
        return max(1, (int) config('captcha.ddos.reporting.cache.limit', 500));
    }
}
