<?php

namespace CodeDart\SlideCaptcha\Tests\Unit;

use CodeDart\SlideCaptcha\Events\SlideCaptchaAttackDetected;
use CodeDart\SlideCaptcha\Reporting\AttackReport;
use CodeDart\SlideCaptcha\Reporting\AttackReportSinkManager;
use CodeDart\SlideCaptcha\Reporting\Sinks\CacheAttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\DatabaseAttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\NoneAttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\S3BatchAttackReportSink;
use CodeDart\SlideCaptcha\Services\SlideCaptchaAttackReporter;
use CodeDart\SlideCaptcha\Services\SlideCaptchaMetrics;
use CodeDart\SlideCaptcha\Tests\Support\CapturingAttackReportSink;
use CodeDart\SlideCaptcha\Tests\Support\ConfigRepository;
use CodeDart\SlideCaptcha\Tests\Support\FakeDatabase;
use CodeDart\SlideCaptcha\Tests\Support\FakeStorage;
use CodeDart\SlideCaptcha\Tests\Support\InMemoryCache;
use PHPUnit\Framework\TestCase;

class AttackReportingTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigRepository::reset();
    }

    public function testNoneSinkDoesNotPersistReports()
    {
        $sink = new NoneAttackReportSink();
        $sink->record($this->report('blocked', 'rate_limit_new'));

        $this->assertTrue(true);
    }

    public function testCacheSinkKeepsTemporaryWindow()
    {
        ConfigRepository::set([
            'captcha.ddos.reporting.cache.limit' => 2,
        ]);

        $sink = new CacheAttackReportSink(new InMemoryCache());
        $sink->record($this->report('observed', 'invalid_position'));
        $sink->record($this->report('observed', 'invalid_rotation'));
        $sink->record($this->report('blocked', 'failure_limit'));

        $reports = $sink->reports();

        $this->assertCount(2, $reports);
        $this->assertSame('invalid_rotation', $reports[0]['reason']);
        $this->assertSame('failure_limit', $reports[1]['reason']);
    }

    public function testDatabaseSinkStoresOneRowPerReport()
    {
        $database = new FakeDatabase();
        $sink = new DatabaseAttackReportSink($database);

        $sink->record($this->report('blocked', 'rate_limit_verify'));

        $this->assertCount(1, $database->rows);
        $this->assertSame('slide_captcha_attack_reports', $database->rows[0]['table']);
        $this->assertSame('rate_limit_verify', $database->rows[0]['reason']);
        $this->assertSame('blocked', $database->rows[0]['action']);
    }

    public function testS3BatchSinkFlushesJsonLinesToStorage()
    {
        $cache = new InMemoryCache();
        $storage = new FakeStorage();
        $sink = new S3BatchAttackReportSink($cache, $storage);

        $sink->record($this->report('observed', 'movement_too_fast'));
        $sink->record($this->report('blocked', 'score_threshold'));

        $result = $sink->flush();

        $this->assertSame(2, $result['flushed']);
        $this->assertCount(1, $storage->puts);
        $this->assertStringContainsString('slide-captcha/attack-reports/', $storage->puts[0]['path']);
        $this->assertCount(2, array_filter(explode("\n", trim($storage->puts[0]['contents']))));
        $this->assertSame([], $sink->pending());
    }

    public function testMetricsAggregateReportsForCustomDashboards()
    {
        $cache = new InMemoryCache();
        $cacheSink = new CacheAttackReportSink($cache);
        $cacheSink->record($this->report('observed', 'invalid_position', 'verify', '10.0.0.1'));
        $cacheSink->record($this->report('blocked', 'rate_limit_new', 'new', '10.0.0.2'));

        $manager = new AttackReportSinkManager();
        $manager->extend('cache', $cacheSink);

        $snapshot = (new SlideCaptchaMetrics($manager))->snapshot(3600, 50);

        $this->assertSame(2, $snapshot['total_events']);
        $this->assertSame(1, $snapshot['blocked_events']);
        $this->assertSame(1, $snapshot['by_reason']['invalid_position']);
        $this->assertSame(1, $snapshot['by_endpoint']['new']);
        $this->assertSame('rate_limit_new', $snapshot['last_event']['reason']);
    }

    public function testReporterDispatchesBestEffortAndStillRecordsWithoutLaravelEvents()
    {
        $sink = new CapturingAttackReportSink();
        $manager = new AttackReportSinkManager();
        $manager->extend('cache', $sink);

        (new SlideCaptchaAttackReporter($manager))->record($this->report('observed', 'validation_failed'));

        $this->assertCount(1, $sink->reports);
        $this->assertSame('validation_failed', $sink->reports[0]['reason']);
    }

    public function testBroadcastEventUsesConfiguredReverbChannelAndAutoMode()
    {
        ConfigRepository::set([
            'broadcasting.default' => 'reverb',
        ]);

        $event = new SlideCaptchaAttackDetected($this->report('blocked', 'rate_limit_new'));

        $this->assertSame(['private-slide-captcha.attacks'], $event->broadcastOn());
        $this->assertSame('slide-captcha.attack', $event->broadcastAs());
        $this->assertTrue($event->broadcastWhen());
        $this->assertSame('rate_limit_new', $event->broadcastWith()['reason']);
    }

    protected function report($action, $reason, $endpoint = 'verify', $ip = '10.0.0.1')
    {
        return AttackReport::normalize([
            'action' => $action,
            'reason' => $reason,
            'endpoint' => $endpoint,
            'severity' => $action === 'blocked' ? 'critical' : 'medium',
            'ip' => $ip,
            'identity_hash' => hash('sha256', $ip),
            'user_agent_hash' => hash('sha256', 'agent'),
            'details' => [
                'test' => true,
            ],
        ]);
    }
}
