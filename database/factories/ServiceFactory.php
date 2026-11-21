<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceKind;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'خدمت '.fake()->unique()->numerify('####'),
            'kind' => ServiceKind::Consultation,
            'default_duration_min' => 30,
            'requires_device' => false,
            'requires_specialist' => true,
            'default_price' => null,
            'is_active' => true,
        ];
    }
}
