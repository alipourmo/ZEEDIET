<?php

use App\Http\Middleware\EnsureAreaAccess;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsurePortalClient;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\NormalizeInput;
use App\Http\Middleware\PortalSession;
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
            // Client portal: a signed-in client on the client guard (spec 7.5 and 8.5).
            'portal' => EnsurePortalClient::class,
        ]);

        $middleware->web(prepend: [
            PortalSession::class,
        ], append: [
            NormalizeInput::class,
            EnsureStaffIsActive::class,
            EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
