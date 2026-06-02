<?php

namespace CodeDart\SlideCaptcha\Reporting\Sinks;

use CodeDart\SlideCaptcha\Contracts\AttackReportSink;

class NoneAttackReportSink implements AttackReportSink
{
    public function record(array $report)
    {
        //
    }
}
