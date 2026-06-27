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
        $middleware->statefulApi();

        $middleware->alias([
            'tenant' => \App\Http\Middleware\EnsureSameTenant::class,
        ]);

        // Optionally pin the guard to every authenticated API request.
        // Kept explicit on route groups so public endpoints stay open.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
