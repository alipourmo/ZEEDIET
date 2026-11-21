<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decimal arithmetic on stored values (decimal strings, two places). Computed in hundredths, so the result has two
 * decimals and zero has no sign.
 */
final class Decimal
{
    /**
     * The signed change from one value to another, e.g. +2.50 or -1.00. Null when either value is missing.
     */
    public static function difference(?string $from, ?string $to): ?string
    {
        if ($from === null || $to === null) {
            return null;
        }

        $hundredths = (int) round(((float) $to - (float) $from) * 100);
        $sign = $hundredths > 0 ? '+' : ($hundredths < 0 ? '-' : '');

        return $sign.number_format(abs($hundredths) / 100, 2, '.', '');
    }
}
