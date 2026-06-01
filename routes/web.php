<?php

use CodeDart\SlideCaptcha\Http\Controllers\SlideCaptchaAssetController;
use CodeDart\SlideCaptcha\Http\Controllers\SlideCaptchaController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('slide-captcha.route_prefix', 'slide-captcha'), '/');
$middleware = config('slide-captcha.middleware', ['web']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::get('/assets/{file}', SlideCaptchaAssetController::class)
            ->where('file', 'slide-captcha\.(?:css|js)')
            ->name('slide-captcha.asset');

        Route::get('/new', [SlideCaptchaController::class, 'new'])->name('slide-captcha.new');
        Route::post('/verify', [SlideCaptchaController::class, 'verify'])->name('slide-captcha.verify');
    });
