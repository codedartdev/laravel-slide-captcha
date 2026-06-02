<?php

namespace CodeDart\SlideCaptcha\Tests\Unit;

use CodeDart\SlideCaptcha\Reporting\AttackReportSinkManager;
use CodeDart\SlideCaptcha\Services\CaptchaDdosProtector;
use CodeDart\SlideCaptcha\Services\SlideCaptchaAttackReporter;
use CodeDart\SlideCaptcha\Tests\Support\CapturingAttackReportSink;
use CodeDart\SlideCaptcha\Tests\Support\ConfigRepository;
use CodeDart\SlideCaptcha\Tests\Support\InMemoryCache;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class DdosProtectorTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigRepository::reset();
    }

    public function testItBlocksNewChallengeRequestsBeforeExpensiveGeneration()
    {
        ConfigRepository::set([
            'captcha.ddos.limits.new' => [
                'max_attempts' => 2,
                'decay_seconds' => 60,
                'block_seconds' => 30,
            ],
            'captcha.ddos.score.threshold' => 999,
        ]);

        $reports = new CapturingAttackReportSink();
        $protector = $this->protector(new InMemoryCache(), $reports);
        $request = $this->request('/slide-captcha/new', 'GET');

        $this->assertTrue($protector->inspect($request, 'new')['allowed']);
        $this->assertTrue($protector->inspect($request, 'new')['allowed']);

        $decision = $protector->inspect($request, 'new');

        $this->assertFalse($decision['allowed']);
        $this->assertSame('ddos_protection', $decision['reason']);
        $this->assertSame(30, $decision['retry_after']);
        $this->assertSame([
            'success' => false,
            'reason' => 'ddos_protection',
            'retry_after' => 30,
        ], $protector->blockedResponse($decision));
        $this->assertSame('blocked', $reports->reports[0]['action']);
        $this->assertSame('rate_limit_new', $reports->reports[0]['reason']);
    }

    public function testItRecordsVerificationFailuresAndBlocksAdaptiveAbuse()
    {
        ConfigRepository::set([
            'captcha.ddos.limits.failures' => [
                'max_attempts' => 1,
                'decay_seconds' => 60,
                'block_seconds' => 45,
            ],
            'captcha.ddos.score.threshold' => 999,
        ]);

        $reports = new CapturingAttackReportSink();
        $protector = $this->protector(new InMemoryCache(), $reports);
        $request = $this->request('/slide-captcha/verify', 'POST');

        $this->assertTrue($protector->recordFailure($request, 'verify', 'movement_too_linear')['allowed']);

        $decision = $protector->recordFailure($request, 'verify', 'movement_too_linear');

        $this->assertFalse($decision['allowed']);
        $this->assertSame(45, $decision['retry_after']);
        $this->assertSame('movement_too_linear', $reports->reports[0]['reason']);
        $this->assertSame('failure_limit', $reports->reports[2]['reason']);
        $this->assertSame('blocked', $reports->reports[2]['action']);
    }

    public function testIdentityUsesIpAndUserAgent()
    {
        $protector = $this->protector(new InMemoryCache(), new CapturingAttackReportSink());

        $first = $protector->identity($this->request('/slide-captcha/new', 'GET', '10.0.0.1', 'Agent A'));
        $second = $protector->identity($this->request('/slide-captcha/new', 'GET', '10.0.0.1', 'Agent B'));

        $this->assertSame('10.0.0.1', $first['ip']);
        $this->assertNotSame($first['user_agent_hash'], $second['user_agent_hash']);
        $this->assertNotSame($first['identity_hash'], $second['identity_hash']);
    }

    protected function protector(InMemoryCache $cache, CapturingAttackReportSink $reports)
    {
        $manager = new AttackReportSinkManager();
        $manager->extend('cache', $reports);

        return new CaptchaDdosProtector(new SlideCaptchaAttackReporter($manager), $cache);
    }

    protected function request($path, $method, $ip = '10.0.0.1', $userAgent = 'SlideCaptchaBot/1.0')
    {
        return Request::create($path, $method, [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
    }
}
