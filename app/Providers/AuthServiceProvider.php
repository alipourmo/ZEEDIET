<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register one Gate per permission key in config/permissions.php.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /** @var list<string> $permissions */
        $permissions = (array) config('permissions.permissions', []);

        foreach ($permissions as $permission) {
            Gate::define($permission, fn (?User $user): bool => $user?->hasPermission($permission) ?? false);
        }
    }
}
