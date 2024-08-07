<!-- ## Usage

This package provides two middlewares for APM functionality:

1. `apm-metrics`: Collects metrics for each request
2. `apm-tracing`: Creates a span for each request and adds basic HTTP information

You can add these middlewares to your routes or middleware groups in your Laravel application:

```php
// In app/Http/Kernel.php

protected $middlewareGroups = [
    'web' => [
        // ... other middlewares
        \Middleware\LaravelApm\Middleware\MetricsMiddleware::class,
        \Middleware\LaravelApm\Middleware\TracingMiddleware::class,
    ],
];

// Or for specific routes in your routes file:
Route::middleware(['apm-metrics', 'apm-tracing'])->group(function () {
    // Your routes here
}); -->

# Installing and Setting Up Our OpenTelemetry Logging Package

This guide will walk you through the process of installing and configuring our OpenTelemetry logging package in your Laravel project.

## Prerequisites

- Laravel project (version 8.x or higher recommended)
- Composer
- PHP 7.4 or higher

## Installation

1. Install the package using Composer:

   ```bash
   composer require Middleware/laravel-apm
   ```

2. Publish the package configuration:

    ```bash
    php artisan vendor:publish --provider="Middleware\LaravelAPM\LaravelAPMServiceProvider"
    ```
    This will create a config/opentelemetry.php file in your project.

## Configuration

1. Open `config/laravel-apm.php` and adjust the settings as needed:

    ```bash
    return [
        'endpoint' => env('APM_EXPORTER_OTLP_ENDPOINT', 'http://localhost:9320'),
        'service_name' => env('APM_SERVICE_NAME', 'laravel-app'),
        'content_type' => 'application/x-protobuf',
        'headers' => [
            'Content-Type' => 'application/x-protobuf'
        ],
    ];
    ```

2. Update your `.env` file with the appropriate values:

    ```bash
    APM_SERVICE_NAME=your-app-name
    ```
    
## Usage

To use the OpenTelemetry logger in your Laravel application:

1. Update your `config/logging.php` to include our custom channel: