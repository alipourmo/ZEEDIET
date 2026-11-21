<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an appointment came from (spec section 6.3). Online booking arrives in V1.0.
 */
enum AppointmentSource: string
{
    case Staff = 'staff';
    case Online = 'online';
}
