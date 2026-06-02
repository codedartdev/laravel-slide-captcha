<?php

namespace CodeDart\SlideCaptcha\Reporting;

use Illuminate\Http\Request;

class AttackReport
{
    public static function make($reason, $action, $endpoint, Request $request = null, array $identity = [], array $context = [])
    {
        $report = [
            'id' => self::id(),
            'occurred_at' => date('c'),
            'occurred_at_timestamp' => time(),
            'action' => $action,
            'severity' => isset($context['severity']) ? (string) $context['severity'] : self::severity($reason, $action),
            'endpoint' => $endpoint,
            'reason' => $reason,
            'ip' => isset($identity['ip']) ? $identity['ip'] : ($request ? $request->ip() : null),
            'identity_hash' => isset($identity['identity_hash']) ? $identity['identity_hash'] : null,
            'user_agent_hash' => isset($identity['user_agent_hash']) ? $identity['user_agent_hash'] : null,
            'session_hash' => isset($identity['session_hash']) ? $identity['session_hash'] : null,
            'retry_after' => isset($context['retry_after']) ? (int) $context['retry_after'] : null,
            'score' => isset($context['score']) ? (int) $context['score'] : null,
            'limit_key' => isset($context['limit_key']) ? (string) $context['limit_key'] : null,
            'request_method' => $request ? $request->method() : null,
            'request_path' => $request ? $request->path() : null,
            'details' => isset($context['details']) && is_array($context['details']) ? $context['details'] : [],
        ];

        return self::normalize($report);
    }

    public static function normalize(array $report)
    {
        $report = array_merge([
            'id' => self::id(),
            'occurred_at' => date('c'),
            'occurred_at_timestamp' => time(),
            'action' => 'observed',
            'severity' => 'low',
            'endpoint' => null,
            'reason' => 'unknown',
            'ip' => null,
            'identity_hash' => null,
            'user_agent_hash' => null,
            'session_hash' => null,
            'retry_after' => null,
            'score' => null,
            'limit_key' => null,
            'request_method' => null,
            'request_path' => null,
            'details' => [],
        ], $report);

        if (! is_array($report['details'])) {
            $decoded = is_string($report['details']) ? json_decode($report['details'], true) : null;
            $report['details'] = is_array($decoded) ? $decoded : [];
        }

        if (! is_string($report['id']) || trim($report['id']) === '') {
            $report['id'] = self::id();
        }

        $report['occurred_at_timestamp'] = (int) $report['occurred_at_timestamp'];

        if ($report['occurred_at_timestamp'] <= 0) {
            $timestamp = is_string($report['occurred_at']) ? strtotime($report['occurred_at']) : false;
            $report['occurred_at_timestamp'] = $timestamp ?: time();
        }

        $report['retry_after'] = $report['retry_after'] === null ? null : (int) $report['retry_after'];
        $report['score'] = $report['score'] === null ? null : (int) $report['score'];

        return $report;
    }

    protected static function severity($reason, $action)
    {
        if ($action === 'blocked') {
            return 'critical';
        }

        $high = ['rate_limit_new', 'rate_limit_verify', 'failure_limit', 'score_threshold'];

        if (in_array($reason, $high, true)) {
            return 'high';
        }

        $medium = [
            'validation_failed',
            'not_found',
            'used',
            'expired',
            'invalid_position',
            'invalid_rotation',
            'movement_too_short',
            'movement_too_fast',
            'movement_too_slow',
            'movement_too_linear',
        ];

        return in_array($reason, $medium, true) ? 'medium' : 'low';
    }

    protected static function id()
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $exception) {
            return str_replace('.', '', uniqid('', true));
        }
    }
}
