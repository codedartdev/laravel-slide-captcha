<?php

namespace CodeDart\SlideCaptcha\Services;

use CodeDart\SlideCaptcha\Reporting\AttackReport;
use CodeDart\SlideCaptcha\Reporting\AttackReportSinkManager;

class SlideCaptchaMetrics
{
    protected $sinks;

    public function __construct(AttackReportSinkManager $sinks = null)
    {
        $this->sinks = $sinks ?: new AttackReportSinkManager();
    }

    public function snapshot($windowSeconds = null, $limit = null)
    {
        $windowSeconds = $windowSeconds === null
            ? max(1, (int) config('captcha.ddos.metrics.window_seconds', 3600))
            : max(1, (int) $windowSeconds);
        $limit = $limit === null
            ? max(1, (int) config('captcha.ddos.metrics.limit', 500))
            : max(1, (int) $limit);
        $now = time();
        $since = $now - $windowSeconds;
        $reports = $this->reports($since, $limit);

        $reports = array_values(array_filter(array_map(function ($report) use ($since) {
            $report = is_array($report) ? AttackReport::normalize($report) : null;

            if (! $report || (int) $report['occurred_at_timestamp'] < $since) {
                return null;
            }

            return $report;
        }, $reports)));

        return [
            'window' => [
                'seconds' => $windowSeconds,
                'from' => date('c', $since),
                'to' => date('c', $now),
            ],
            'total_events' => count($reports),
            'blocked_events' => $this->countWhere($reports, 'action', 'blocked'),
            'by_severity' => $this->counts($reports, 'severity'),
            'by_endpoint' => $this->counts($reports, 'endpoint'),
            'by_reason' => $this->counts($reports, 'reason'),
            'top_ips' => $this->top($reports, 'ip'),
            'top_identities' => $this->top($reports, 'identity_hash'),
            'last_event' => count($reports) > 0 ? $reports[count($reports) - 1] : null,
            'events' => array_slice($reports, -1 * $limit),
        ];
    }

    protected function reports($since, $limit)
    {
        $names = $this->sinks->configuredNames();
        $reports = [];

        if (in_array('cache', $names, true)) {
            $sink = $this->sinks->sink('cache');

            if ($sink && method_exists($sink, 'reports')) {
                $reports = $sink->reports($limit);
            }
        }

        if (count($reports) === 0 && in_array('database', $names, true)) {
            $sink = $this->sinks->sink('database');

            if ($sink && method_exists($sink, 'reports')) {
                $reports = $sink->reports($since, $limit);
            }
        }

        if (count($reports) === 0 && in_array('s3_batch', $names, true)) {
            $sink = $this->sinks->sink('s3_batch');

            if ($sink && method_exists($sink, 'pending')) {
                $reports = $sink->pending();
            }
        }

        return is_array($reports) ? $reports : [];
    }

    protected function countWhere(array $reports, $field, $value)
    {
        $count = 0;

        foreach ($reports as $report) {
            if (isset($report[$field]) && $report[$field] === $value) {
                $count++;
            }
        }

        return $count;
    }

    protected function counts(array $reports, $field)
    {
        $counts = [];

        foreach ($reports as $report) {
            $value = isset($report[$field]) && $report[$field] !== null ? (string) $report[$field] : 'unknown';
            $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1;
        }

        arsort($counts);

        return $counts;
    }

    protected function top(array $reports, $field)
    {
        $counts = $this->counts(array_filter($reports, function ($report) use ($field) {
            return isset($report[$field]) && $report[$field] !== null && $report[$field] !== '';
        }), $field);

        return array_slice($counts, 0, 10, true);
    }
}
