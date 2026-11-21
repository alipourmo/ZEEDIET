<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that checks access to a panel: area:admin, area:operator, area:specialist.
 * The check is the area.* Gate from config/permissions.php.
 *
 * Guests get 403 for now. S1-T2 adds the login route and redirects guests there.
 */
class EnsureAreaAccess
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();

        abort_unless($user !== null && Gate::forUser($user)->allows('area.'.$area), 403);

        return $next($request);
    }
}
