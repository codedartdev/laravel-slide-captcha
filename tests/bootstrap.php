<?php

namespace {
    require_once __DIR__ . '/../vendor/autoload.php';
}

namespace CodeDart\SlideCaptcha\Services {
    function config($key = null, $default = null)
    {
        return \CodeDart\SlideCaptcha\Tests\Support\ConfigRepository::get($key, $default);
    }
}

namespace CodeDart\SlideCaptcha\Reporting {
    function config($key = null, $default = null)
    {
        return \CodeDart\SlideCaptcha\Tests\Support\ConfigRepository::get($key, $default);
    }
}

namespace CodeDart\SlideCaptcha\Reporting\Sinks {
    function config($key = null, $default = null)
    {
        return \CodeDart\SlideCaptcha\Tests\Support\ConfigRepository::get($key, $default);
    }
}

namespace CodeDart\SlideCaptcha\Events {
    function config($key = null, $default = null)
    {
        return \CodeDart\SlideCaptcha\Tests\Support\ConfigRepository::get($key, $default);
    }
}

namespace CodeDart\SlideCaptcha\Support {
    function config($key = null, $default = null)
    {
        return \CodeDart\SlideCaptcha\Tests\Support\ConfigRepository::get($key, $default);
    }
}

namespace CodeDart\SlideCaptcha\Tests\Support {
    final class ConfigRepository
    {
        private static $items = [];

        public static function reset()
        {
            self::$items = [
                'captcha.ttl' => 120,
                'captcha.tolerance' => 8,
                'captcha.rotation.enabled' => true,
                'captcha.rotation.step_degrees' => 15,
                'captcha.rotation.max_degrees' => 90,
                'captcha.rotation.tolerance_degrees' => 8,
                'captcha.movement.min_points' => 3,
                'captcha.movement.min_duration_ms' => 100,
                'captcha.movement.max_duration_ms' => 5000,
                'captcha.movement.max_same_y_ratio' => 0.8,
                'captcha.ddos.enabled' => true,
                'captcha.ddos.mode' => 'adaptive',
                'captcha.ddos.identity.session' => true,
                'captcha.ddos.limits.new' => [
                    'max_attempts' => 60,
                    'decay_seconds' => 60,
                    'block_seconds' => 300,
                ],
                'captcha.ddos.limits.verify' => [
                    'max_attempts' => 120,
                    'decay_seconds' => 60,
                    'block_seconds' => 300,
                ],
                'captcha.ddos.limits.failures' => [
                    'max_attempts' => 20,
                    'decay_seconds' => 60,
                    'block_seconds' => 600,
                ],
                'captcha.ddos.score.threshold' => 80,
                'captcha.ddos.score.decay_seconds' => 120,
                'captcha.ddos.score.block_seconds' => 600,
                'captcha.ddos.reporting.sinks' => ['cache'],
                'captcha.ddos.reporting.cache.key' => 'slide_captcha_attack_reports:cache',
                'captcha.ddos.reporting.cache.ttl' => 3600,
                'captcha.ddos.reporting.cache.limit' => 500,
                'captcha.ddos.reporting.database.table' => 'slide_captcha_attack_reports',
                'captcha.ddos.reporting.s3_batch.cache_key' => 'slide_captcha_attack_reports:s3_batch',
                'captcha.ddos.reporting.s3_batch.cache_ttl' => 3600,
                'captcha.ddos.reporting.s3_batch.disk' => 's3',
                'captcha.ddos.reporting.s3_batch.path' => 'slide-captcha/attack-reports/{date}/{datetime}-{uuid}.jsonl',
                'captcha.ddos.reporting.s3_batch.visibility' => 'private',
                'captcha.ddos.broadcast.enabled' => 'auto',
                'captcha.ddos.broadcast.channel' => 'private-slide-captcha.attacks',
                'captcha.ddos.broadcast.event' => 'slide-captcha.attack',
                'captcha.ddos.metrics.window_seconds' => 3600,
                'captcha.ddos.metrics.limit' => 500,
            ];
        }

        public static function set(array $items)
        {
            self::$items = array_merge(self::$items, $items);
        }

        public static function get($key = null, $default = null)
        {
            if ($key === null) {
                return self::$items;
            }

            return array_key_exists($key, self::$items) ? self::$items[$key] : $default;
        }
    }

    ConfigRepository::reset();
}
