<?php

namespace CodeDart\SlideCaptcha\Reporting\Sinks;

use CodeDart\SlideCaptcha\Contracts\AttackReportSink;
use CodeDart\SlideCaptcha\Reporting\AttackReport;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseAttackReportSink implements AttackReportSink
{
    protected $database;

    public function __construct($database = null)
    {
        $this->database = $database;
    }

    public function record(array $report)
    {
        try {
            $this->database()->table($this->table())->insert($this->row(AttackReport::normalize($report)));
        } catch (Throwable $exception) {
            // Reporting must never break CAPTCHA generation or validation.
        }
    }

    public function reports($sinceTimestamp = null, $limit = 500)
    {
        try {
            $query = $this->database()->table($this->table())
                ->orderBy('occurred_at', 'desc')
                ->limit(max(1, (int) $limit));

            if ($sinceTimestamp !== null) {
                $query->where('occurred_at', '>=', date('Y-m-d H:i:s', (int) $sinceTimestamp));
            }

            $rows = $query->get();
        } catch (Throwable $exception) {
            return [];
        }

        $reports = [];

        foreach ($rows as $row) {
            $reports[] = $this->reportFromRow($row);
        }

        return array_reverse(array_values(array_filter($reports)));
    }

    protected function database()
    {
        return $this->database ?: DB::connection();
    }

    protected function table()
    {
        return (string) config('captcha.ddos.reporting.database.table', 'slide_captcha_attack_reports');
    }

    protected function row(array $report)
    {
        return [
            'report_id' => $report['id'],
            'occurred_at' => date('Y-m-d H:i:s', (int) $report['occurred_at_timestamp']),
            'action' => $report['action'],
            'severity' => $report['severity'],
            'endpoint' => $report['endpoint'],
            'reason' => $report['reason'],
            'ip' => $report['ip'],
            'identity_hash' => $report['identity_hash'],
            'user_agent_hash' => $report['user_agent_hash'],
            'session_hash' => $report['session_hash'],
            'retry_after' => $report['retry_after'],
            'score' => $report['score'],
            'limit_key' => $report['limit_key'],
            'request_method' => $report['request_method'],
            'request_path' => $report['request_path'],
            'details' => json_encode($report['details'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    protected function reportFromRow($row)
    {
        $row = (array) $row;
        $timestamp = isset($row['occurred_at']) ? strtotime((string) $row['occurred_at']) : time();

        return AttackReport::normalize([
            'id' => isset($row['report_id']) ? $row['report_id'] : null,
            'occurred_at' => date('c', $timestamp ?: time()),
            'occurred_at_timestamp' => $timestamp ?: time(),
            'action' => isset($row['action']) ? $row['action'] : 'observed',
            'severity' => isset($row['severity']) ? $row['severity'] : 'low',
            'endpoint' => isset($row['endpoint']) ? $row['endpoint'] : null,
            'reason' => isset($row['reason']) ? $row['reason'] : 'unknown',
            'ip' => isset($row['ip']) ? $row['ip'] : null,
            'identity_hash' => isset($row['identity_hash']) ? $row['identity_hash'] : null,
            'user_agent_hash' => isset($row['user_agent_hash']) ? $row['user_agent_hash'] : null,
            'session_hash' => isset($row['session_hash']) ? $row['session_hash'] : null,
            'retry_after' => isset($row['retry_after']) ? $row['retry_after'] : null,
            'score' => isset($row['score']) ? $row['score'] : null,
            'limit_key' => isset($row['limit_key']) ? $row['limit_key'] : null,
            'request_method' => isset($row['request_method']) ? $row['request_method'] : null,
            'request_path' => isset($row['request_path']) ? $row['request_path'] : null,
            'details' => isset($row['details']) ? $row['details'] : [],
        ]);
    }
}
