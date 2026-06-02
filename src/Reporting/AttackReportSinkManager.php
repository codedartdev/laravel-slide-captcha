<?php

namespace CodeDart\SlideCaptcha\Reporting;

use CodeDart\SlideCaptcha\Contracts\AttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\CacheAttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\DatabaseAttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\NoneAttackReportSink;
use CodeDart\SlideCaptcha\Reporting\Sinks\S3BatchAttackReportSink;

class AttackReportSinkManager
{
    protected $custom = [];

    public function extend($name, AttackReportSink $sink)
    {
        $this->custom[$name] = $sink;
    }

    public function sinks()
    {
        $names = $this->configuredNames();
        $sinks = [];

        foreach ($names as $name) {
            $sink = $this->sink($name);

            if ($sink) {
                $sinks[] = $sink;
            }
        }

        return count($sinks) > 0 ? $sinks : [new CacheAttackReportSink()];
    }

    public function sink($name)
    {
        if (isset($this->custom[$name])) {
            return $this->custom[$name];
        }

        switch ($name) {
            case 'none':
                return new NoneAttackReportSink();
            case 'cache':
                return new CacheAttackReportSink();
            case 'database':
                return new DatabaseAttackReportSink();
            case 's3_batch':
                return new S3BatchAttackReportSink();
        }

        return null;
    }

    public function configuredNames()
    {
        $configured = config('captcha.ddos.reporting.sinks', ['cache']);

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        if (! is_array($configured) || count($configured) === 0) {
            return ['cache'];
        }

        $names = [];

        foreach ($configured as $name) {
            $name = trim((string) $name);

            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        if (count($names) > 1) {
            $names = array_values(array_filter($names, function ($name) {
                return $name !== 'none';
            }));
        }

        return count($names) > 0 ? $names : ['cache'];
    }
}
