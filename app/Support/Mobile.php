<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Iranian mobile number normalisation.
 *
 * Accepts +98912..., 0098912..., 0912..., 912... and Persian/Arabic digits.
 * Returns the canonical 11-digit form 09xxxxxxxxx, or null when the input is not a valid mobile.
 */
final class Mobile
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $value = Digits::toAscii(trim($input));
        $value = preg_replace('/[\s\-]+/u', '', $value) ?? '';

        if (str_starts_with($value, '+98')) {
            $value = substr($value, 3);
        } elseif (str_starts_with($value, '0098')) {
            $value = substr($value, 4);
        }

        // A bare 10-digit national number such as 9121234567 gets its missing leading zero.
        if (strlen($value) === 10 && $value[0] === '9') {
            $value = '0'.$value;
        }

        return preg_match('/^09\d{9}$/', $value) === 1 ? $value : null;
    }
}
