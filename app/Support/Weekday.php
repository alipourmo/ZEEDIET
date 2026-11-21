<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Weekday numbering of spec section 6.2: 0 = Saturday ... 6 = Friday. Carbon counts from Sunday, so it is shifted here.
 */
final class Weekday
{
    public const COUNT = 7;

    public static function fromDate(CarbonInterface $date): int
    {
        return ($date->dayOfWeek + 1) % 7;
    }
}
