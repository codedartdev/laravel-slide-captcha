<?php

namespace CodeDart\SlideCaptcha\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CaptchaDdosProtector
{
    protected $reporter;

    protected $cache;

    public function __construct(SlideCaptchaAttackReporter $reporter, $cache = null)
    {
        $this->reporter = $reporter;
        $this->cache = $cache;
    }

    public function inspect(Request $request, $endpoint)
    {
        if (! $this->enabled()) {
            return $this->allow($request);
        }

        $identity = $this->identity($request);
        $blocked = $this->existingBlock($identity);

        if ($blocked) {
            return $this->blocked($request, $identity, $endpoint, 'ddos_protection', $blocked['retry_after'], [
                'trigger_reason' => isset($blocked['reason']) ? $blocked['reason'] : 'existing_block',
            ]);
        }

        $limit = $this->limit($endpoint);
        $limitKey = $this->key('rate:' . $endpoint, $identity);
        $count = $this->incrementCounter($limitKey, $limit['decay_seconds']);

        if ($count > $limit['max_attempts']) {
            return $this->triggerBlock($request, $identity, $endpoint, 'rate_limit_' . $endpoint, $limit['block_seconds'], [
                'attempts' => $count,
                'max_attempts' => $limit['max_attempts'],
                'decay_seconds' => $limit['decay_seconds'],
            ], $limitKey);
        }

        $score = $this->addScore($identity, $this->scoreWeight('request_' . $endpoint, 1));

        if ($score >= $this->scoreThreshold()) {
            return $this->triggerBlock($request, $identity, $endpoint, 'score_threshold', $this->scoreBlockSeconds(), [
                'score' => $score,
                'threshold' => $this->scoreThreshold(),
            ], $this->key('score', $identity), $score);
        }

        return $this->allow($request, $identity);
    }

    public function recordFailure(Request $request, $endpoint, $reason, array $details = [])
    {
        if (! $this->enabled()) {
            return $this->allow($request);
        }

        $identity = $this->identity($request);
        $failureLimit = $this->limit('failures');
        $failureKey = $this->key('failures', $identity);
        $failures = $this->incrementCounter($failureKey, $failureLimit['decay_seconds']);
        $score = $this->addScore($identity, $this->scoreWeight($reason, $this->scoreWeight('default_failure', 4)));

        $this->reporter->report($reason, 'observed', $endpoint, $request, $identity, [
            'score' => $score,
            'limit_key' => $failureKey,
            'details' => array_merge($details, [
                'failures' => $failures,
                'failure_limit' => $failureLimit['max_attempts'],
            ]),
        ]);

        if ($failures > $failureLimit['max_attempts']) {
            return $this->triggerBlock($request, $identity, $endpoint, 'failure_limit', $failureLimit['block_seconds'], [
                'failures' => $failures,
                'max_attempts' => $failureLimit['max_attempts'],
                'decay_seconds' => $failureLimit['decay_seconds'],
                'last_reason' => $reason,
            ], $failureKey, $score);
        }

        if ($score >= $this->scoreThreshold()) {
            return $this->triggerBlock($request, $identity, $endpoint, 'score_threshold', $this->scoreBlockSeconds(), [
                'score' => $score,
                'threshold' => $this->scoreThreshold(),
                'last_reason' => $reason,
            ], $this->key('score', $identity), $score);
        }

        return $this->allow($request, $identity);
    }

    public function identity(Request $request)
    {
        $ip = (string) $request->ip();
        $userAgent = (string) $request->userAgent();
        $userAgentHash = hash('sha256', $userAgent);
        $sessionHash = null;

        if ($this->usesSessionIdentity()) {
            try {
                if ($request->hasSession()) {
                    $sessionHash = hash('sha256', (string) $request->session()->getId());
                }
            } catch (Throwable $exception) {
                $sessionHash = null;
            }
        }

        $parts = [$ip, $userAgentHash, $sessionHash ?: 'no-session'];

        return [
            'ip' => $ip,
            'user_agent_hash' => $userAgentHash,
            'session_hash' => $sessionHash,
            'identity_hash' => hash('sha256', implode('|', $parts)),
        ];
    }

    public function blockedResponse(array $decision)
    {
        return [
            'success' => false,
            'reason' => 'ddos_protection',
            'retry_after' => isset($decision['retry_after']) ? (int) $decision['retry_after'] : 60,
        ];
    }

    protected function triggerBlock(Request $request, array $identity, $endpoint, $reason, $blockSeconds, array $details = [], $limitKey = null, $score = null)
    {
        $retryAfter = max(1, (int) $blockSeconds);

        if ($this->monitorOnly()) {
            $this->reporter->report($reason, 'observed', $endpoint, $request, $identity, [
                'retry_after' => $retryAfter,
                'score' => $score,
                'limit_key' => $limitKey,
                'severity' => 'high',
                'details' => array_merge($details, [
                    'mode' => 'monitor',
                ]),
            ]);

            return $this->allow($request, $identity);
        }

        try {
            $this->cache()->put($this->key('block', $identity), [
                'reason' => $reason,
                'retry_until' => time() + $retryAfter,
            ], $retryAfter);
        } catch (Throwable $exception) {
            //
        }

        return $this->blocked($request, $identity, $endpoint, $reason, $retryAfter, $details, $limitKey, $score);
    }

    protected function blocked(Request $request, array $identity, $endpoint, $reason, $retryAfter, array $details = [], $limitKey = null, $score = null)
    {
        $this->reporter->report($reason, 'blocked', $endpoint, $request, $identity, [
            'retry_after' => $retryAfter,
            'score' => $score,
            'limit_key' => $limitKey,
            'details' => $details,
        ]);

        return [
            'allowed' => false,
            'identity' => $identity,
            'reason' => 'ddos_protection',
            'trigger_reason' => $reason,
            'retry_after' => max(1, (int) $retryAfter),
        ];
    }

    protected function allow(Request $request, array $identity = null)
    {
        return [
            'allowed' => true,
            'identity' => $identity ?: $this->identity($request),
        ];
    }

    protected function existingBlock(array $identity)
    {
        try {
            $payload = $this->cache()->get($this->key('block', $identity));
        } catch (Throwable $exception) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $retryUntil = isset($payload['retry_until']) ? (int) $payload['retry_until'] : time() + 60;

        return [
            'reason' => isset($payload['reason']) ? $payload['reason'] : 'existing_block',
            'retry_after' => max(1, $retryUntil - time()),
        ];
    }

    protected function addScore(array $identity, $weight)
    {
        return $this->incrementCounter(
            $this->key('score', $identity),
            $this->scoreDecaySeconds(),
            max(0, (int) $weight)
        );
    }

    protected function incrementCounter($key, $decaySeconds, $amount = 1)
    {
        $decaySeconds = max(1, (int) $decaySeconds);
        $amount = max(1, (int) $amount);

        try {
            $cache = $this->cache();
            $cache->add($key . ':timer', time() + $decaySeconds, $decaySeconds);
            $cache->add($key, 0, $decaySeconds);
            $value = $cache->increment($key, $amount);

            if (! is_numeric($value)) {
                $value = (int) $cache->get($key, 0) + $amount;
                $cache->put($key, $value, $decaySeconds);
            }

            return (int) $value;
        } catch (Throwable $exception) {
            return 0;
        }
    }

    protected function key($prefix, array $identity)
    {
        return 'slide_captcha_ddos:' . $prefix . ':' . $identity['identity_hash'];
    }

    protected function limit($name)
    {
        $defaults = [
            'new' => [
                'max_attempts' => 60,
                'decay_seconds' => 60,
                'block_seconds' => 300,
            ],
            'verify' => [
                'max_attempts' => 120,
                'decay_seconds' => 60,
                'block_seconds' => 300,
            ],
            'failures' => [
                'max_attempts' => 20,
                'decay_seconds' => 60,
                'block_seconds' => 600,
            ],
        ];

        $default = isset($defaults[$name]) ? $defaults[$name] : $defaults['new'];
        $configured = config('captcha.ddos.limits.' . $name, []);

        if (! is_array($configured)) {
            $configured = [];
        }

        $limit = array_merge($default, $configured);

        return [
            'max_attempts' => max(1, (int) $limit['max_attempts']),
            'decay_seconds' => max(1, (int) $limit['decay_seconds']),
            'block_seconds' => max(1, (int) $limit['block_seconds']),
        ];
    }

    protected function scoreWeight($reason, $default)
    {
        return (int) config('captcha.ddos.score.weights.' . $reason, $default);
    }

    protected function scoreThreshold()
    {
        return max(1, (int) config('captcha.ddos.score.threshold', 80));
    }

    protected function scoreDecaySeconds()
    {
        return max(1, (int) config('captcha.ddos.score.decay_seconds', 120));
    }

    protected function scoreBlockSeconds()
    {
        return max(1, (int) config('captcha.ddos.score.block_seconds', 600));
    }

    protected function monitorOnly()
    {
        return (string) config('captcha.ddos.mode', 'adaptive') === 'monitor';
    }

    protected function enabled()
    {
        $enabled = config('captcha.ddos.enabled', true);

        return ! ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0');
    }

    protected function usesSessionIdentity()
    {
        $enabled = config('captcha.ddos.identity.session', true);

        return ! ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0');
    }

    protected function cache()
    {
        if ($this->cache) {
            return $this->cache;
        }

        $store = config('captcha.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }
}
