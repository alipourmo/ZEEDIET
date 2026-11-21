<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after the change is committed (spec section 9). The SMS subscriber listens to it.
 */
final class AppointmentCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}
}
