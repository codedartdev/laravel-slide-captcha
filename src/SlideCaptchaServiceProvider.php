<?php

namespace CodeDart\SlideCaptcha;

use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Support\MovementAnalyzer;
use Illuminate\Support\ServiceProvider;

class SlideCaptchaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/slide-captcha.php', 'slide-captcha');

        $this->app->singleton(CaptchaGenerator::class, function () {
            return new CaptchaGenerator();
        });

        $this->app->singleton(CaptchaValidator::class, function ($app) {
            return new CaptchaValidator($app->make(MovementAnalyzer::class));
        });
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'slide-captcha');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/slide-captcha.php' => config_path('slide-captcha.php'),
        ], 'slide-captcha-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/slide-captcha'),
        ], 'slide-captcha-views');
    }
}
