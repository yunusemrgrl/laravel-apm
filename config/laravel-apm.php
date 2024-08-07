<?php

return [
    'endpoint' => env('APM_EXPORTER_OTLP_ENDPOINT', 'http://localhost:9320'),
    'service_name' => env('APM_SERVICE_NAME', 'laravel-app'),
    'content_type' => 'application/x-protobuf',
    'headers' => [
        'Content-Type' => 'application/x-protobuf'
    ],
];