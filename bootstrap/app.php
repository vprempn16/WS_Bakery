<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Sanctum SPA: session + CSRF cookies for configured stateful domains.
        $middleware->statefulApi();

        $middleware->alias([
            'check.org' => \App\Http\Middleware\CheckOrganizationContext::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // Default API throttle; stricter limits applied per-route where needed.
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
