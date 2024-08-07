<?php

namespace Middleware\LaravelApm\Middleware;

use Closure;
use Illuminate\Http\Request;
use Middleware\LaravelApm\MetricsManager;
use OpenTelemetry\API\Metrics\ObserverInterface;
use Illuminate\Support\Facades\Log;

class MetricsMiddleware
{
    private $metricsManager;
    private $meterProvider;
    private $requestsCounter;
    private $responseTimeGauge;
    private $requestSizeGauge;
    private $responseSizeGauge;
    private $statusCounter;
    private static $lastResponseTime = 0;
    private static $lastRequestSize = 0;
    private static $lastResponseSize = 0;


    public function __construct(MetricsManager $metricsManager)
    {
        $this->metricsManager = $metricsManager;

        // Set up callbacks for observable gauges
        // $this->metricsManager->getResponseTimeGauge()->observe(static function ($observer) {
        //     $observer->observe(self::$lastResponseSize);
        // });

        // $this->metricsManager->getRequestSizeGauge()->observe(static function ($observer) {
        //     $observer->observe(self::$lastRequestSize);
        // });

        // $this->metricsManager->getResponseSizeGauge()->observe(static function ($observer) {
        //     $observer->observe(self::$lastResponseSize);
        // });
    }

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        $route = $request->route() ? $request->route()->uri() : 'unknown';

        $this->recordMetrics($request, $response, $duration, $route);

        return $response;
    }

    private function recordMetrics(Request $request, $response, $duration, $route)
    {
        // Update static properties
        self::$lastResponseTime = $duration;
        self::$lastRequestSize = strlen($request->getContent());
        self::$lastResponseSize = strlen($response->getContent());

        // Record other metrics
        $this->metricsManager->getRequestsCounter()->add(1, [
            'method' => $request->method(),
            'route' => $route
        ]);

        $this->metricsManager->getStatusCounter()->add(1, [
            'status' => (string) $response->getStatusCode()
        ]);

        return $response;
    }


}