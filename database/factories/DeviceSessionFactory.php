<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceSessionStatus;
use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSession>
 */
class DeviceSessionFactory extends Factory
{
    protected $model = DeviceSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'appointment_id' => null,
            'package_id' => null,
            'device_id' => Device::factory(),
            'operator_id' => User::factory()->role(StaffRole::Operator),
            // Unique per run, so the (client, device, number) key never collides in a test.
            'session_number' => fake()->unique()->numberBetween(1, 60000),
            'status' => DeviceSessionStatus::Done,
            'parameters' => null,
            'note' => null,
            'performed_at' => now('Asia/Tehran'),
        ];
    }
}
