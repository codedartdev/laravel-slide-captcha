<?php

namespace CodeDart\SlideCaptcha\Console;

use CodeDart\SlideCaptcha\Reporting\Sinks\S3BatchAttackReportSink;
use Illuminate\Console\Command;

class FlushAttackReportsCommand extends Command
{
    protected $signature = 'slide-captcha:flush-attack-reports';

    protected $description = 'Flush queued slide CAPTCHA attack reports to the configured S3-compatible disk.';

    public function handle()
    {
        $result = (new S3BatchAttackReportSink())->flush();

        if (! empty($result['error'])) {
            $this->error('Could not flush slide CAPTCHA attack reports: ' . $result['error']);

            return 1;
        }

        if ((int) $result['flushed'] === 0) {
            $this->info('No slide CAPTCHA attack reports to flush.');

            return 0;
        }

        $this->info(sprintf(
            'Flushed %d slide CAPTCHA attack reports to %s.',
            (int) $result['flushed'],
            $result['path']
        ));

        return 0;
    }
}
