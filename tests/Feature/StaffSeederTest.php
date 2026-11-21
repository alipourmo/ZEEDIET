<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Database\Seeders\StaffSeeder;

test('the staff seeder creates one account per role that must change its password', function () {
    $this->seed(StaffSeeder::class);

    expect(User::count())->toBe(4);

    foreach (StaffRole::cases() as $role) {
        $user = User::query()->where('role', $role)->sole();

        expect($user->must_change_password)->toBeTrue()
            ->and($user->is_active)->toBeTrue()
            ->and($user->password)->toStartWith('$2y$');
    }
});

test('running the staff seeder twice does not create duplicates or reset passwords', function () {
    $this->seed(StaffSeeder::class);
    $before = User::query()->orderBy('id')->pluck('password', 'mobile')->all();

    $this->seed(StaffSeeder::class);

    expect(User::count())->toBe(4)
        ->and(User::query()->orderBy('id')->pluck('password', 'mobile')->all())->toBe($before);
});
