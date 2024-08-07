<?php

namespace Middleware\LaravelApm\Commands;

use Illuminate\Console\Command;
use Middleware\LaravelApm\LaravelApm;

class ExportMetrics extends Command
{
    protected $signature = 'laravel-apm:export-metrics';
    protected $description = 'Send collected metrics to the APM collector';

    public function handle(LaravelApm $apm)
    {
        $this->info('Sending metrics to collector...');
        $result = $apm->exportMetrics();
        $this->info($result ? 'Metrics sent successfully.' : 'Failed to send metrics.');
    }
}