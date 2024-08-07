<?php

namespace Middleware\LaravelApm;

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\ObservableGaugeInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;

class MetricsManager
{
    private $meterProvider;
    private $requestsCounter;
    private $responseTimeGauge;
    private $requestSizeGauge;
    private $responseSizeGauge;
    private $statusCounter;

    public function __construct(MeterProviderInterface $meterProvider)
    {
        $this->meterProvider = $meterProvider;
        $this->initializeMetrics();
    }

    private function initializeMetrics()
    {
        $meter = $this->meterProvider->getMeter('laravel.http');

        // Counter (Cumulative)
        $this->requestsCounter = $meter->createCounter(
            'laravel.http.requests.total',
            'requests',
            'Total number of HTTP requests',
        );

        // Gauge (no temporality needed)
        // $this->responseTimeGauge = $meter->createObservableGauge(
        //     'laravel.http.response.duration',
        //     'seconds',
        //     'HTTP response time',
        // );

        // $this->requestSizeGauge = $meter->createObservableGauge(
        //     'laravel.http.request.size',
        //     'bytes',
        //     'Size of HTTP requests',
        // );

        // $this->responseSizeGauge = $meter->createObservableGauge(
        //     'laravel.http.response.size',
        //     'bytes',
        //     'Size of HTTP responses',
        // );

        // Counter (Cumulative)
        $this->statusCounter = $meter->createCounter(
            'laravel.http.response.status',
            'responses',
            'HTTP response status codes',
        );
    }

    public function getRequestsCounter(): CounterInterface
    {
        return $this->requestsCounter;
    }

    // public function getResponseTimeGauge(): ObservableGaugeInterface
    // {
    //     return $this->responseTimeGauge;
    // }

    // public function getRequestSizeGauge(): ObservableGaugeInterface
    // {
    //     return $this->requestSizeGauge;
    // }

    // public function getResponseSizeGauge(): ObservableGaugeInterface
    // {
    //     return $this->responseSizeGauge;
    // }

    public function getStatusCounter(): CounterInterface
    {
        return $this->statusCounter;
    }
}