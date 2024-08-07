<?php

namespace Middleware\LaravelApm\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelApm extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-apm';
    }
}