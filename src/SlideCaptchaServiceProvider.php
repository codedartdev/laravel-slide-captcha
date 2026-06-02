<?php

namespace CodeDart\SlideCaptcha;

use CodeDart\SlideCaptcha\Services\CaptchaGenerator;
use CodeDart\SlideCaptcha\Services\CaptchaValidator;
use CodeDart\SlideCaptcha\Services\CaptchaDdosProtector;
use CodeDart\SlideCaptcha\Services\SlideCaptchaAttackReporter;
use CodeDart\SlideCaptcha\Services\SlideCaptchaMetrics;
use CodeDart\SlideCaptcha\Reporting\AttackReportSinkManager;
use CodeDart\SlideCaptcha\Console\FlushAttackReportsCommand;
use CodeDart\SlideCaptcha\Support\MovementAnalyzer;
use Illuminate\Support\ServiceProvider;

class SlideCaptchaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/captcha.php', 'captcha');

        $this->app->singleton(CaptchaGenerator::class, function () {
            return new CaptchaGenerator();
        });

        $this->app->singleton(CaptchaValidator::class, function ($app) {
            return new CaptchaValidator($app->make(MovementAnalyzer::class));
        });

        $this->app->singleton(AttackReportSinkManager::class, function () {
            return new AttackReportSinkManager();
        });

        $this->app->singleton(SlideCaptchaAttackReporter::class, function ($app) {
            return new SlideCaptchaAttackReporter($app->make(AttackReportSinkManager::class));
        });

        $this->app->singleton(SlideCaptchaMetrics::class, function ($app) {
            return new SlideCaptchaMetrics($app->make(AttackReportSinkManager::class));
        });

        $this->app->singleton(CaptchaDdosProtector::class, function ($app) {
            return new CaptchaDdosProtector($app->make(SlideCaptchaAttackReporter::class));
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
            __DIR__ . '/../config/captcha.php' => config_path('captcha.php'),
        ], ['captcha-config', 'slide-captcha-config']);

        $migrationTarget = function_exists('database_path')
            ? database_path('migrations/' . date('Y_m_d_His') . '_create_slide_captcha_attack_reports_table.php')
            : 'database/migrations/' . date('Y_m_d_His') . '_create_slide_captcha_attack_reports_table.php';

        $this->publishes([
            __DIR__ . '/../database/migrations/create_slide_captcha_attack_reports_table.php' => $migrationTarget,
        ], 'slide-captcha-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/slide-captcha'),
        ], 'slide-captcha-views');

        if (method_exists($this, 'commands')) {
            $this->commands([
                FlushAttackReportsCommand::class,
            ]);
        }
    }
}
