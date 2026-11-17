<?php

use App\Http\Middleware\EnsureAreaAccess;
use App\Http\Middleware\NormalizeInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Panel access: route middleware 'area:admin', 'area:operator' or 'area:specialist' (spec section 8).
        $middleware->alias([
            'area' => EnsureAreaAccess::class,
        ]);

        $middleware->web(append: [
            NormalizeInput::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
