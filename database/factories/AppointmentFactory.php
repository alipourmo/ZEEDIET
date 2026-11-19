<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now('Asia/Tehran')->addDays(7)->setTime(10, 0);

        return [
            'client_id' => Client::factory(),
            'service_id' => Service::factory(),
            'specialist_id' => null,
            'device_id' => null,
            'room_id' => null,
            'starts_at' => $start,
            'ends_at' => (clone $start)->addMinutes(30),
            'status' => AppointmentStatus::Scheduled,
            'source' => AppointmentSource::Staff,
            'cancel_reason' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
