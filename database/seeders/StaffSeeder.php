<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One staff account per role (spec section 7.6). Each new account gets a random password that is printed once
 * to the console and never stored in plain text or committed. The account must change it on first login.
 *
 * Mobile numbers are placeholders; the clinic replaces them before go-live.
 */
class StaffSeeder extends Seeder
{
    /** @var array<string, string> role => placeholder mobile */
    private const ACCOUNTS = [
        'admin' => '09000000001',
        'secretary' => '09000000002',
        'operator' => '09000000003',
        'specialist' => '09000000004',
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $role => $mobile) {
            if (User::query()->where('mobile', $mobile)->exists()) {
                continue;
            }

            $password = Str::random(16);

            User::create([
                'name' => __('seeders.staff.'.$role),
                'mobile' => $mobile,
                'password' => $password,
                'role' => StaffRole::from($role),
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $this->command?->warn(sprintf(
                'Created %s account: mobile %s, temporary password %s (shown once; change it at first login).',
                $role,
                $mobile,
                $password,
            ));
        }
    }
}
