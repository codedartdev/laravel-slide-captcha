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
                'slide-captcha.ttl' => 120,
                'slide-captcha.tolerance' => 8,
                'slide-captcha.rotation.enabled' => true,
                'slide-captcha.rotation.step_degrees' => 15,
                'slide-captcha.rotation.max_degrees' => 90,
                'slide-captcha.rotation.tolerance_degrees' => 8,
                'slide-captcha.movement.min_points' => 3,
                'slide-captcha.movement.min_duration_ms' => 100,
                'slide-captcha.movement.max_duration_ms' => 5000,
                'slide-captcha.movement.max_same_y_ratio' => 0.8,
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
