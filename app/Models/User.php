<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A staff account (admin, secretary, operator or specialist). Clients are a separate model.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'mobile',
        'password',
        'role',
        'is_active',
        'must_change_password',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Whether this user's role grants the permission. Used by the Gates in AuthServiceProvider.
     * Controllers and views must not compare roles directly; they use can: and @can.
     */
    public function hasPermission(string $permission): bool
    {
        /** @var StaffRole $role */
        $role = $this->role;
        $granted = config('permissions.roles.'.$role->value, []);

        return is_array($granted) && in_array($permission, $granted, true);
    }

    /**
     * The panel this user lands on after login, chosen by permission (not by role name).
     */
    public function defaultPanelRoute(): string
    {
        if ($this->hasPermission('area.admin')) {
            return 'admin.home';
        }

        if ($this->hasPermission('area.operator')) {
            return 'operator.home';
        }

        return $this->hasPermission('area.specialist') ? 'specialist.home' : 'login';
    }
}
