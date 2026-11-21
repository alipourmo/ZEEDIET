<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the portal's session the 30-day lifetime of spec 7.5. It runs before the session starts, so only /my-account
 * requests change. The 24-hour idle limit is enforced by EnsurePortalClient, not by the cookie.
 */
class PortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('my-account', 'my-account/*')) {
            config(['session.lifetime' => (int) config('clinic.portal.session_absolute_days') * 24 * 60]);
        }

        return $next($request);
    }
}
