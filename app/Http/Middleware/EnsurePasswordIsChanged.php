<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends staff with a seeded (temporary) password to the change-password page before anything else (spec section 7.6).
 */
class EnsurePasswordIsChanged
{
    /** Routes a staff member may use before changing the password. */
    private const ALLOWED = ['password.change', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if ($user instanceof User && $user->must_change_password && ! in_array($routeName, self::ALLOWED, true)) {
            return redirect()->route('password.change')->with('warning', __('auth.must_change_password'));
        }

        return $next($request);
    }
}
