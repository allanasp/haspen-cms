<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register custom API middleware
        $middleware->alias([
            'tenant.isolation' => \App\Http\Middleware\TenantIsolation::class,
            'api.auth' => \App\Http\Middleware\ApiAuthentication::class,
            'api.rate_limit' => \App\Http\Middleware\ApiRateLimit::class,
            'api.logging' => \App\Http\Middleware\ApiLogging::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
