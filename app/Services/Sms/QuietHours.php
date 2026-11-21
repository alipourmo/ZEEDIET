<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Carbon\CarbonImmutable;

/**
 * Quiet hours for marketing-type SMS (spec section 9): no marketing message between 21:00 and 08:00, Asia/Tehran.
 */
final class QuietHours
{
    /**
     * The moment a marketing message may go out, or null when it may go out now.
     */
    public static function nextAllowed(CarbonImmutable $now): ?CarbonImmutable
    {
        $start = (int) config('clinic.quiet_hours.start');
        $end = (int) config('clinic.quiet_hours.end');
        $hour = (int) $now->format('G');

        $quiet = $start > $end
            ? ($hour >= $start || $hour < $end)
            : ($hour >= $start && $hour < $end);

        if (! $quiet) {
            return null;
        }

        $next = $now->setTime($end, 0);

        return $next->lessThanOrEqualTo($now) ? $next->addDay() : $next;
    }
}
