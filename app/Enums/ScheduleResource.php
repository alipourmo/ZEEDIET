<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a weekly schedule belongs to (spec section 6.2). The owner is a user (specialist), a device or a room.
 */
enum ScheduleResource: string
{
    case User = 'user';
    case Device = 'device';
    case Room = 'room';
}
