<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The password used by the factory. Tests only; never used for seeded accounts (spec section 7.6).
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile' => '09'.fake()->unique()->numerify('#########'),
            'password' => static::$password ??= Hash::make('password'),
            'role' => StaffRole::Secretary,
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    public function role(StaffRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
