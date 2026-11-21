<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Enums\Gender;
use App\Enums\PregnancyStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'C-'.fake()->unique()->numerify('######'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'mobile' => '09'.fake()->unique()->numerify('#########'),
            'birth_date' => null,
            'gender' => Gender::Female,
            'height_cm' => null,
            'pregnancy_status' => PregnancyStatus::None,
            'referral_source' => null,
            'internal_note' => null,
            'consent_data_at' => now(),
            'consent_photos_at' => null,
            'status' => ClientStatus::Active,
            'created_by' => User::factory(),
        ];
    }
}
