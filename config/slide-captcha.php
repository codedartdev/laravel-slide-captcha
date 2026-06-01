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
];
