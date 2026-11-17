<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Spec section 10.5: every route is either public (explicit allowlist below) or protected by auth or an area middleware.
 * A new route that is neither fails this test until it is added to the allowlist on purpose.
 */
const PUBLIC_ROUTE_URIS = [
    '/',
    'tools/jalali',
    'tools/mobile',
    '_styleguide',
    'healthz',
    'up',
    'login',
    'logout',
];

test('every route is public by allowlist or protected by auth or an area middleware', function () {
    $unprotected = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (in_array($uri, PUBLIC_ROUTE_URIS, true)) {
            continue;
        }

        // The middleware as declared on the route (aliases such as 'auth' and 'area:admin'), not the resolved classes.
        $middleware = $route->middleware();
        $protected = in_array('auth', $middleware, true)
            || collect($middleware)->contains(fn (string $name): bool => str_starts_with($name, 'area:'));

        if (! $protected) {
            $unprotected[] = implode('|', $route->methods()).' '.$uri;
        }
    }

    expect($unprotected)->toBe([]);
});

test('every public allowlist entry is still a real route', function () {
    $uris = collect(Route::getRoutes())->map(fn ($route): string => $route->uri())->all();

    foreach (PUBLIC_ROUTE_URIS as $uri) {
        expect($uris)->toContain($uri);
    }
});
