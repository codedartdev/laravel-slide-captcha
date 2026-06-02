<?php

namespace CodeDart\SlideCaptcha\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class SlideCaptchaAttackDetected implements ShouldBroadcastNow
{
    public $report;

    public function __construct(array $report)
    {
        $this->report = $report;
    }

    public function broadcastOn()
    {
        return [(string) config('captcha.ddos.broadcast.channel', 'private-slide-captcha.attacks')];
    }

    public function broadcastAs()
    {
        return (string) config('captcha.ddos.broadcast.event', 'slide-captcha.attack');
    }

    public function broadcastWith()
    {
        return $this->report;
    }

    public function broadcastWhen()
    {
        $enabled = config('captcha.ddos.broadcast.enabled', 'auto');

        if ($enabled === true || $enabled === 'true' || $enabled === 1 || $enabled === '1') {
            return true;
        }

        if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
            return false;
        }

        return (string) config('broadcasting.default', '') === 'reverb';
    }
}
