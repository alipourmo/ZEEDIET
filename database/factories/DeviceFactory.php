<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'دستگاه '.fake()->unique()->numerify('####'),
            'model' => null,
            'contraindications' => [],
            'is_active' => true,
            'notes' => null,
        ];
    }
}
