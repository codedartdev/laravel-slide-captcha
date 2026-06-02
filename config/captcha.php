<?php

return [
    'enabled' => env('SLIDE_CAPTCHA_ENABLED', true),

    'cache_store' => env('SLIDE_CAPTCHA_CACHE_STORE', null),
    'ttl' => (int) env('SLIDE_CAPTCHA_TTL', 120),

    'image_width' => (int) env('SLIDE_CAPTCHA_IMAGE_WIDTH', 320),
    'image_height' => (int) env('SLIDE_CAPTCHA_IMAGE_HEIGHT', 180),
    'piece_min_size' => (int) env('SLIDE_CAPTCHA_PIECE_MIN_SIZE', 42),
    'piece_max_size' => (int) env('SLIDE_CAPTCHA_PIECE_MAX_SIZE', 58),
    'tolerance' => (int) env('SLIDE_CAPTCHA_TOLERANCE', 8),

    'rotation' => [
        'enabled' => env('SLIDE_CAPTCHA_ROTATION_ENABLED', true),
        'step_degrees' => (int) env('SLIDE_CAPTCHA_ROTATION_STEP_DEGREES', 15),
        'max_degrees' => (int) env('SLIDE_CAPTCHA_ROTATION_MAX_DEGREES', 90),
        'tolerance_degrees' => (int) env('SLIDE_CAPTCHA_ROTATION_TOLERANCE_DEGREES', 8),
    ],

    'route_prefix' => env('SLIDE_CAPTCHA_ROUTE_PREFIX', 'slide-captcha'),
    'middleware' => array_filter(explode(',', env('SLIDE_CAPTCHA_MIDDLEWARE', 'web'))),

    'storage_disk' => env('SLIDE_CAPTCHA_STORAGE_DISK', 's3'),
    'backgrounds_path' => env('SLIDE_CAPTCHA_BACKGROUNDS_PATH', null),
    'generated_path' => env('SLIDE_CAPTCHA_GENERATED_PATH', 'slide-captcha/generated'),
    'temporary_url_ttl' => (int) env('SLIDE_CAPTCHA_TEMPORARY_URL_TTL', 300),
    'asset_cache_ttl' => (int) env('SLIDE_CAPTCHA_ASSET_CACHE_TTL', 86400),

    'validate_movement' => env('SLIDE_CAPTCHA_VALIDATE_MOVEMENT', true),

    'movement' => [
        'min_points' => (int) env('SLIDE_CAPTCHA_MOVEMENT_MIN_POINTS', 8),
        'min_duration_ms' => (int) env('SLIDE_CAPTCHA_MOVEMENT_MIN_DURATION_MS', 250),
        'max_duration_ms' => (int) env('SLIDE_CAPTCHA_MOVEMENT_MAX_DURATION_MS', 15000),
        'max_same_y_ratio' => (float) env('SLIDE_CAPTCHA_MOVEMENT_MAX_SAME_Y_RATIO', 0.9),
    ],

    'ddos' => [
        'enabled' => env('SLIDE_CAPTCHA_DDOS_ENABLED', true),
        'mode' => env('SLIDE_CAPTCHA_DDOS_MODE', 'adaptive'),

        'identity' => [
            'session' => env('SLIDE_CAPTCHA_DDOS_IDENTITY_SESSION', true),
        ],

        'limits' => [
            'new' => [
                'max_attempts' => (int) env('SLIDE_CAPTCHA_DDOS_NEW_MAX_ATTEMPTS', 60),
                'decay_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_NEW_DECAY_SECONDS', 60),
                'block_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_NEW_BLOCK_SECONDS', 300),
            ],
            'verify' => [
                'max_attempts' => (int) env('SLIDE_CAPTCHA_DDOS_VERIFY_MAX_ATTEMPTS', 120),
                'decay_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_VERIFY_DECAY_SECONDS', 60),
                'block_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_VERIFY_BLOCK_SECONDS', 300),
            ],
            'failures' => [
                'max_attempts' => (int) env('SLIDE_CAPTCHA_DDOS_FAILURE_MAX_ATTEMPTS', 20),
                'decay_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_FAILURE_DECAY_SECONDS', 60),
                'block_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_FAILURE_BLOCK_SECONDS', 600),
            ],
        ],

        'score' => [
            'threshold' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_THRESHOLD', 80),
            'decay_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_DECAY_SECONDS', 120),
            'block_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_BLOCK_SECONDS', 600),
            'weights' => [
                'request_new' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_REQUEST_NEW', 1),
                'request_verify' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_REQUEST_VERIFY', 1),
                'default_failure' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_DEFAULT_FAILURE', 4),
                'validation_failed' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_VALIDATION_FAILED', 5),
                'not_found' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_NOT_FOUND', 5),
                'used' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_USED', 6),
                'expired' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_EXPIRED', 3),
                'invalid_position' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_INVALID_POSITION', 4),
                'invalid_rotation' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_INVALID_ROTATION', 4),
                'movement_too_short' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_MOVEMENT_TOO_SHORT', 5),
                'movement_too_fast' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_MOVEMENT_TOO_FAST', 6),
                'movement_too_slow' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_MOVEMENT_TOO_SLOW', 3),
                'movement_too_linear' => (int) env('SLIDE_CAPTCHA_DDOS_SCORE_MOVEMENT_TOO_LINEAR', 6),
            ],
        ],

        'broadcast' => [
            'enabled' => env('SLIDE_CAPTCHA_DDOS_BROADCAST_ENABLED', 'auto'),
            'channel' => env('SLIDE_CAPTCHA_DDOS_BROADCAST_CHANNEL', 'private-slide-captcha.attacks'),
            'event' => env('SLIDE_CAPTCHA_DDOS_BROADCAST_EVENT', 'slide-captcha.attack'),
        ],

        'reporting' => [
            'sinks' => array_filter(array_map('trim', explode(',', env('SLIDE_CAPTCHA_DDOS_REPORTING_SINKS', 'cache')))),
            'cache' => [
                'key' => env('SLIDE_CAPTCHA_DDOS_CACHE_KEY', 'slide_captcha_attack_reports:cache'),
                'ttl' => (int) env('SLIDE_CAPTCHA_DDOS_CACHE_TTL', 3600),
                'limit' => (int) env('SLIDE_CAPTCHA_DDOS_CACHE_LIMIT', 500),
            ],
            'database' => [
                'table' => env('SLIDE_CAPTCHA_DDOS_DATABASE_TABLE', 'slide_captcha_attack_reports'),
            ],
            's3_batch' => [
                'cache_key' => env('SLIDE_CAPTCHA_DDOS_S3_BATCH_CACHE_KEY', 'slide_captcha_attack_reports:s3_batch'),
                'cache_ttl' => (int) env('SLIDE_CAPTCHA_DDOS_S3_BATCH_CACHE_TTL', 3600),
                'disk' => env('SLIDE_CAPTCHA_DDOS_S3_BATCH_DISK', env('SLIDE_CAPTCHA_STORAGE_DISK', 's3')),
                'path' => env('SLIDE_CAPTCHA_DDOS_S3_BATCH_PATH', 'slide-captcha/attack-reports/{date}/{datetime}-{uuid}.jsonl'),
                'visibility' => env('SLIDE_CAPTCHA_DDOS_S3_BATCH_VISIBILITY', 'private'),
            ],
        ],

        'metrics' => [
            'window_seconds' => (int) env('SLIDE_CAPTCHA_DDOS_METRICS_WINDOW_SECONDS', 3600),
            'limit' => (int) env('SLIDE_CAPTCHA_DDOS_METRICS_LIMIT', 500),
        ],
    ],
];
