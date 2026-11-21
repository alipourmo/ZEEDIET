<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal access (spec 7.5 and 8.5). A guest goes to the portal sign-in page. A signed-in client is signed out when the
 * session is more than 30 days old since sign-in, or has been idle for more than 24 hours. Otherwise the idle clock
 * restarts. The staff guard is not checked here: a staff session does not open the portal.
 */
class EnsurePortalClient
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('client')->check()) {
            return redirect()->route('portal.login');
        }

        $now = now()->timestamp;
        $loginAt = $request->session()->get('portal.login_at');
        $seenAt = $request->session()->get('portal.seen_at');

        $expired = ! is_int($loginAt) || ! is_int($seenAt)
            || $now - $loginAt > (int) config('clinic.portal.session_absolute_days') * 86400
            || $now - $seenAt > (int) config('clinic.portal.session_idle_hours') * 3600;

        if ($expired) {
            Auth::guard('client')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->with('error', __('portal.session_expired'));
        }

        $request->session()->put('portal.seen_at', $now);

        return $next($request);
    }
}
