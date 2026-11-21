<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceKind;
use App\Models\Client;
use App\Models\Service;
use App\Models\SessionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionPackage>
 */
class SessionPackageFactory extends Factory
{
    protected $model = SessionPackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'service_id' => Service::factory()->state([
                'kind' => ServiceKind::DeviceSession,
                'requires_device' => true,
                'requires_specialist' => false,
            ]),
            'total_sessions' => 10,
            'price' => null,
            'purchased_at' => now('Asia/Tehran')->toDateString(),
            'created_by' => null,
        ];
    }
}
