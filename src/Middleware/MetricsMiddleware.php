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
    private static $responseTimes = [];
    private $totalDuration = 0;
    private $requestCount = 0;
    private $totalRequestSize = 0;
    private $totalResponseSize = 0;

    public function __construct(MetricsManager $metricsManager)
    {
        $this->metricsManager = $metricsManager;

        // $this->metricsManager->getResponseSizeGauge()->observe(function (ObserverInterface $observer): void {
        //     foreach (self::$responseTimes as $labels => $time) {
        //         $observer->observe($time, json_decode($labels, true));
        //     }
        //     self::$responseTimes = [];
        // });
    }

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;
        $route = $request->route() ? $request->route()->uri() : 'unknown';

        // Only record metrics once per request
        if (!$request->attributes->has('metrics_recorded')) {
            $this->recordMetrics($request, $response, $duration, $route);
            $request->attributes->set('metrics_recorded', true);
        }

        return $response;
    }

    private function recordMetrics(Request $request, $response, $duration, $route)
    {
        $this->metricsManager->getRequestsCounter()->add(1, [
            'method' => $request->method(),
            'route' => $route
        ]);

        // $labels = json_encode(['method' => $request->method(), 'route' => $route]);
        // self::$responseTimes[$labels] = $duration;

        // $this->metricsManager->getStatusCounter()->add(1, [
        //     'status' => (string) $response->getStatusCode()
        // ]);

        // $requestSize = $request->headers->get('Content-Length', 0);
        // $responseSize = $response->headers->get('Content-Length', 0);

        // $this->metricsManager->getRequestSizeUpDownCounter()->add($requestSize, [
        //     'method' => $request->method()
        // ]);

        // $this->metricsManager->getResponseSizeUpDownCounter()->add($responseSize, [
        //     'method' => $request->method()
        // ]);

        $this->totalDuration += $duration;
        $this->requestCount++;
        $avgDuration = $this->totalDuration / $this->requestCount;

        $this->metricsManager->getResponseTimeGauge()->observe(function ($observer) use ($avgDuration) {
            $observer->observe($avgDuration);
        });

        $requestSize = strlen($request->getContent());
        $this->totalRequestSize += $requestSize;
        $avgRequestSize = $this->totalRequestSize / $this->requestCount;

        $this->metricsManager->getRequestSizeGauge()->observe(function ($observer) use ($avgRequestSize) {
            $observer->observe($avgRequestSize);
        });

        $responseSize = strlen($response->getContent());
        $this->totalResponseSize += $responseSize;
        $avgResponseSize = $this->totalResponseSize / $this->requestCount;

        $this->metricsManager->getResponseSizeGauge()->observe(function ($observer) use ($avgResponseSize) {
            $observer->observe($avgResponseSize);
        });

        $this->metricsManager->getStatusCounter()->add(1, [
            'status' => (string) $response->getStatusCode()
        ]);

        Log::info("Request metrics", [
            'method' => $request->method(),
            'route' => $route,
            'duration' => $duration,
            'avgDuration' => $avgDuration,
            'requestSize' => $requestSize,
            'responseSize' => $responseSize,
            'status' => $response->getStatusCode()
        ]);
    }
}