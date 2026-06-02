<?php

namespace CodeDart\SlideCaptcha\Services;

use CodeDart\SlideCaptcha\Events\SlideCaptchaAttackDetected;
use CodeDart\SlideCaptcha\Reporting\AttackReport;
use CodeDart\SlideCaptcha\Reporting\AttackReportSinkManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Throwable;

class SlideCaptchaAttackReporter
{
    protected $sinks;

    public function __construct(AttackReportSinkManager $sinks = null)
    {
        $this->sinks = $sinks ?: new AttackReportSinkManager();
    }

    public function report($reason, $action, $endpoint, Request $request = null, array $identity = [], array $context = [])
    {
        return $this->record(AttackReport::make($reason, $action, $endpoint, $request, $identity, $context));
    }

    public function record(array $report)
    {
        $report = AttackReport::normalize($report);

        $this->dispatch($report);

        foreach ($this->sinks->sinks() as $sink) {
            try {
                $sink->record($report);
            } catch (Throwable $exception) {
                // Attack reporting is observability; it must not block CAPTCHA traffic.
            }
        }

        return $report;
    }

    public function sinks()
    {
        return $this->sinks;
    }

    protected function dispatch(array $report)
    {
        try {
            if (function_exists('event')) {
                event(new SlideCaptchaAttackDetected($report));

                return;
            }

            Event::dispatch(new SlideCaptchaAttackDetected($report));
        } catch (Throwable $exception) {
            // Some package tests and slim installs do not boot Laravel's event dispatcher.
        }
    }
}
