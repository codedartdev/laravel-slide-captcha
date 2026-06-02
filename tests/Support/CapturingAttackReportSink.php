<?php

namespace CodeDart\SlideCaptcha\Tests\Support;

use CodeDart\SlideCaptcha\Contracts\AttackReportSink;

class CapturingAttackReportSink implements AttackReportSink
{
    public $reports = [];

    public function record(array $report)
    {
        $this->reports[] = $report;
    }
}
