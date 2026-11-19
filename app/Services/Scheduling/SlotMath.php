<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

/**
 * Pure time arithmetic for availability. Times are minutes after midnight, so no database is needed to test it.
 */
final class SlotMath
{
    /**
     * "09:30" or "09:30:00" to minutes after midnight.
     */
    public static function toMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time) + [1 => '0']);

        return $hour * 60 + $minute;
    }

    /**
     * Whether [aStart, aEnd) and [bStart, bEnd) share any time. Touching intervals do not overlap:
     * an appointment that ends at 10:00 does not clash with one that starts at 10:00.
     */
    public static function overlaps(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $aEnd > $bStart;
    }

    /**
     * Whether [start, end) lies completely inside [windowStart, windowEnd].
     */
    public static function isInside(int $start, int $end, int $windowStart, int $windowEnd): bool
    {
        return $start >= $windowStart && $end <= $windowEnd;
    }
}
