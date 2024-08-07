<?php

namespace Middleware\LaravelApm;

use Illuminate\Support\ServiceProvider;
use Middleware\LaravelApm\Commands\ExportMetrics;
use OpenTelemetry\Contrib\Instrumentation\Laravel\LaravelInstrumentation;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor as LogProcessor;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Factory as LaravelHttpFactory;
use GuzzleHttp\Psr7\HttpFactory as GuzzleHttpFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class LaravelApmServiceProvider extends ServiceProvider
{

    private $contentType;
    private $headers;

    private $endpoint;

    public function register()
    {
        $this->endpoint = config('laravel-apm.endpoint');
        $this->contentType = config('laravel-apm.content_type');
        $this->headers = config('laravel-apm.headers');

        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-apm.php', 'laravel-apm');

        $this->app->singleton(ExportingReader::class, function ($app) {
            $transportFactory = $this->getTransportFactory();

            $transport = $transportFactory->create($this->endpoint . '/v1/metrics', $this->contentType, $this->headers);

            $exporter = new MetricExporter($transport, Temporality::CUMULATIVE);

            return new ExportingReader($exporter);
        });

        $this->app->singleton(MetricsManager::class, function ($app) {
            return new MetricsManager($app->make(MeterProviderInterface::class));
        });

        $this->app->singleton(TracerProviderInterface::class, function ($app) {
            return $this->createTracerProvider();
        });

        $this->app->singleton(LoggerProviderInterface::class, function ($app) {
            return $this->createLoggerProvider();
        });

        $this->app->singleton(MeterProviderInterface::class, function ($app) {
            return $this->createMeterProvider();
        });


        $this->app->singleton('laravel-apm', function ($app) {
            return new LaravelApm(
                $app->make(TracerProviderInterface::class),
                $app->make(LoggerProviderInterface::class),
                $app->make(MeterProviderInterface::class),
                $app->make(ExportingReader::class)
            );
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-apm.php' => config_path('laravel-apm.php'),
        ], 'config');

        try {
            if ($this->app->runningInConsole()) {
                $this->commands([
                    ExportMetrics::class,
                ]);
            }

            LaravelInstrumentation::register();
            $this->configureLogging();
            $this->app['router']->aliasMiddleware('apm-metrics', \Middleware\LaravelApm\Middleware\MetricsMiddleware::class);

            $this->app->terminating(function () {
                $apm = $this->app->make('laravel-apm');
                $apm->exportMetrics();
            });

            $this->app['router']->aliasMiddleware('apm-metrics', \Middleware\LaravelApm\Middleware\MetricsMiddleware::class);
            $this->app['router']->aliasMiddleware('apm-tracing', \Middleware\LaravelApm\Middleware\TracingMiddleware::class);
        } catch (\Exception $e) {
            Log::error('Failed to boot Laravel APM service provider: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    private function createTracerProvider()
    {
        $transportFactory = $this->getTransportFactory();

        $transport = $transportFactory->create($this->endpoint . '/v1/traces', $this->contentType, $this->headers);

        $exporter = new SpanExporter($transport);

        $spanProcessor = new SimpleSpanProcessor($exporter);

        return TracerProvider::builder()
            ->addSpanProcessor($spanProcessor)
            ->setResource(ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => config('laravel-apm.service_name'),
            ]))))
            ->build();
    }

    private function createLoggerProvider()
    {
        $transportFactory = $this->getTransportFactory();

        $transport = $transportFactory->create($this->endpoint . '/v1/logs', $this->contentType, $this->headers);

        $exporter = new LogsExporter($transport);

        return LoggerProvider::builder()
            ->addLogRecordProcessor(new LogProcessor($exporter))
            ->build();
    }

    private function createMeterProvider()
    {
        $reader = $this->app->make(ExportingReader::class);

        $serviceName = config('laravel-apm.service_name');

        return MeterProvider::builder()
            ->addReader($reader)
            ->setResource(ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
                ResourceAttributes::SERVICE_NAME => $serviceName,
            ]))))
            ->build();
    }


    private function configureLogging()
    {
        $loggerProvider = $this->app->make(LoggerProviderInterface::class);
        $otelHandler = new \OpenTelemetry\Contrib\Logs\Monolog\Handler($loggerProvider, 'debug');
        Log::pushHandler($otelHandler);
    }

    protected function createHttpFactories(): array
    {
        // if (class_exists(LaravelHttpFactory::class)) {
        //     $factory = new LaravelHttpFactory();
        //     return [
        //         'client' => $factory->buildClient(),
        //         'requestFactory' => $factory,
        //         'streamFactory' => $factory,
        //     ];
        // }

        if (class_exists(GuzzleHttpFactory::class)) {
            $factory = new GuzzleHttpFactory();
            return [
                'client' => new \GuzzleHttp\Client(),
                'requestFactory' => $factory,
                'streamFactory' => $factory,
            ];
        }

        throw new \RuntimeException('No suitable HTTP factories found. Please install Guzzle or use Laravel 8.0+');
    }

    private function getTransportFactory(): TransportFactoryInterface
    {
        $factories = $this->createHttpFactories();

        return new PsrTransportFactory(
            $factories['client'],
            $factories['requestFactory'],
            $factories['streamFactory']
        );
    }
}
