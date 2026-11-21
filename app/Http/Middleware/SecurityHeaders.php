<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every web response (spec sections 10.1, 10.11 and 10.12).
 *
 * The CSP allows only this site: scripts, styles, fonts and images from 'self' (public/ holds the Chart.js vendor file
 * and the Vazirmatn font). Nothing is inline. Robots are told not to index any page of this app.
 */
class SecurityHeaders
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        ."font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; "
        ."frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Content-Security-Policy', self::CSP);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        // Spec 10.1: HSTS in production only, so local HTTP development is not broken.
        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
