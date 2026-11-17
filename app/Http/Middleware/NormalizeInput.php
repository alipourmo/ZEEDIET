<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Digits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Converts Persian and Arabic digits to ASCII, and Arabic ي/ك to Persian ی/ک, in every text input
 * before validation (spec section 7.9). Applies to query strings, form bodies and JSON bodies.
 *
 * Password fields are left unchanged, so the password the user typed is the one that is checked.
 */
class NormalizeInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace(self::normalize($request->query->all()));

        if ($request->isJson()) {
            $request->json()->replace(self::normalize($request->json()->all()));
        } else {
            $request->request->replace(self::normalize($request->request->all()));
        }

        return $next($request);
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private static function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = self::normalize($value);
            } elseif (is_string($value) && ! self::isPasswordField($key)) {
                $input[$key] = Digits::toAscii($value);
            }
        }

        return $input;
    }

    private static function isPasswordField(int|string $key): bool
    {
        return str_contains(strtolower((string) $key), 'password');
    }
}
