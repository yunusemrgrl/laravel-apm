<?php

namespace Middleware\LaravelApm;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use Illuminate\Support\Facades\Log;

class LaravelApm
{
    protected $tracerProvider;

    protected $loggerProvider;

    protected $meterProvider;

    protected $exportingReader;


    public function __construct(
        TracerProviderInterface $tracerProvider,
        LoggerProviderInterface $loggerProvider,
        MeterProviderInterface $meterProvider,
        ExportingReader $exportingReader
    ) {
        $this->tracerProvider = $tracerProvider;
        $this->loggerProvider = $loggerProvider;
        $this->meterProvider = $meterProvider;
        $this->exportingReader = $exportingReader;
    }

    public function getTracerProvider()
    {
        return $this->tracerProvider;
    }

    public function getLoggerProvider()
    {
        return $this->loggerProvider;
    }

    public function getMeterProvider()
    {
        return $this->meterProvider;
    }

    public function exportMetrics()
    {
        try {
            $result = $this->exportingReader->collect();
            return $result;
        } catch (\Exception $e) {
            Log::error('Error exporting metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}