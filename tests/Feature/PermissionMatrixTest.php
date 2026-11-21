<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The grants from the spec section 5 table. Written out here on purpose, separately from config/permissions.php,
 * so that a wrong edit to the config fails the test.
 *
 * @return array<string, list<string>>
 */
function specPermissionMatrix(): array
{
    return [
        'admin' => [
            'client.view', 'client.create', 'client.update', 'client.delete',
            'medical.view', 'medical.update', 'medical.view_alerts',
            'appointment.view_all', 'appointment.view_today', 'appointment.manage',
            'session.record', 'session.view',
            'measurement.record', 'measurement.review', 'measurement.view',
            'note.manage', 'payment.view', 'payment.record', 'payment.reverse', 'package.manage',
            'report.admin', 'audit.view', 'user.manage', 'settings.manage', 'catalog.manage',
            'sms.log.view', 'sms.templates.manage',
            'area.admin',
        ],
        'secretary' => [
            'client.view', 'client.create', 'client.update',
            'medical.view_alerts',
            'appointment.view_all', 'appointment.view_today', 'appointment.manage',
            'session.view',
            'payment.view', 'payment.record', 'package.manage',
            'sms.log.view',
            'area.admin',
        ],
        'operator' => [
            'client.view',
            'medical.view_alerts',
            'appointment.view_today',
            'session.record', 'session.view',
            'measurement.record', 'measurement.view',
            'area.operator',
        ],
        'specialist' => [
            'client.view', 'client.create', 'client.update',
            'medical.view', 'medical.update', 'medical.view_alerts',
            'appointment.view_today', 'appointment.view_own',
            'session.record', 'session.view',
            'measurement.record', 'measurement.review', 'measurement.view',
            'note.manage',
            'area.specialist',
        ],
    ];
}

test('every role is granted exactly the permissions in the spec matrix', function () {
    foreach (specPermissionMatrix() as $role => $expected) {
        $user = User::factory()->role(StaffRole::from($role))->create();
        $gate = Gate::forUser($user);

        $allowed = array_values(array_filter(
            config('permissions.permissions'),
            fn (string $permission): bool => $gate->allows($permission),
        ));

        expect($allowed)->toEqualCanonicalizing($expected);
    }
});

test('a Gate is registered for every permission key in the config', function () {
    foreach (config('permissions.permissions') as $permission) {
        expect(Gate::has($permission))->toBeTrue();
    }
});

test('every role in the config is a StaffRole and grants only known permissions', function () {
    $known = config('permissions.permissions');

    foreach (config('permissions.roles') as $role => $keys) {
        expect(StaffRole::tryFrom((string) $role))->not->toBeNull();
        expect(array_diff($keys, $known))->toBeEmpty();
    }
});

test('a guest has no permissions', function () {
    expect(Gate::forUser(null)->allows('client.view'))->toBeFalse();
    expect(Gate::forUser(null)->allows('area.admin'))->toBeFalse();
});
