<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of a device session (spec section 6.3). Only done sessions use up a package (spec 6.3, remaining sessions).
 */
enum DeviceSessionStatus: string
{
    case Done = 'done';
    case Cancelled = 'cancelled';
    case Missed = 'missed';
}
