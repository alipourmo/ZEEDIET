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
    'my-account/login',
    'my-account/verify',
];

// The portal URIs are public only as far as sign-in goes. Everything else under my-account needs the 'portal' guard.
const PORTAL_PUBLIC_URIS = ['my-account/login', 'my-account/verify'];

// Each protected portal route must list the 'portal' middleware, including the home page.

test('the portal pages that need sign-in are behind the portal middleware', function () {
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'my-account') || in_array($uri, PORTAL_PUBLIC_URIS, true)) {
            continue;
        }

        // Only the sign-in pages are public. The portal home is the bare 'my-account' URI, which is not public.
        // The declared middleware names: the 'portal' alias is what routes/web.php attaches (see bootstrap/app.php).
        $declared = $route->middleware();
        expect(in_array('portal', $declared, true))->toBeTrue('portal route not behind portal middleware: '.$uri.' ['.implode(',', $declared).']');
    }
});

test('every protected route also answers a guest with a redirect, never a page', function () {
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (in_array($uri, PUBLIC_ROUTE_URIS, true) || ! in_array('GET', $route->methods(), true) || str_contains($uri, '{')) {
            continue;
        }

        $response = $this->get('/'.$uri);

        expect($response->getStatusCode())->toBeIn([302, 403, 404], 'guest reached /'.$uri);
    }
});

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
            || collect($middleware)->contains(fn (string $name): bool => str_starts_with($name, 'area:') || str_starts_with($name, 'can:') || $name === 'portal');

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
