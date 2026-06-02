<?php

namespace CodeDart\SlideCaptcha\Contracts;

interface AttackReportSink
{
    public function record(array $report);
}
