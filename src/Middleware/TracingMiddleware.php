<?php

namespace Middleware\LaravelApm\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Illuminate\Support\Facades\Log;

class TracingMiddleware
{
    protected $tracerProvider;

    public function __construct(TracerProviderInterface $tracerProvider)
    {
        $this->tracerProvider = $tracerProvider;
    }

    public function handle(Request $request, Closure $next)
    {
        $finalSegment = $request->segment($request->segments() ? count($request->segments()) : 0);

        $tracer = $this->tracerProvider->getTracer('mw-tracer');
        $span = $tracer->spanBuilder($request->getUri())->startSpan();
        $scope = $span->activate();


        $span->setAttribute('http.method', $request->method());
        $span->setAttribute('http.url', $request->fullUrl());
        $span->setAttribute('http.route', $request->route() ? $request->route()->getName() : 'unknown');

        $request->attributes->set('mw_span', $span);

        try {
            $response = $next($request);

            $span->setAttribute('http.status_code', $response->getStatusCode());

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());

            throw $e;
        } finally {
            $span->end();
            $scope->detach();
        }
    }
}