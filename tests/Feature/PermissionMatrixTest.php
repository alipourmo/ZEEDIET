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

/**
 * Spec section 10.5: one line per page a role must not reach. A role that has no grant for a page gets 403 (an area
 * the role may not enter) or 404 (a record it may not see), and never a 200. The page URL is built with a real record
 * where a parameter is needed.
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function forbiddenPages(): array
{
    return [
        'secretary cannot open the specialist panel' => ['secretary', 'specialist.home', 'GET'],
        'secretary cannot open the operator panel' => ['secretary', 'operator.home', 'GET'],
        'secretary cannot open the daily report' => ['secretary', 'admin.reports.daily', 'GET'],
        'secretary cannot open the audit log' => ['secretary', 'admin.audit.index', 'GET'],
        'secretary cannot manage users' => ['secretary', 'admin.users.index', 'GET'],
        'operator cannot open the admin panel' => ['operator', 'admin.home', 'GET'],
        'operator cannot list clients' => ['operator', 'admin.clients.index', 'GET'],
        'operator cannot open the payment ledger' => ['operator', 'admin.payments.index', 'GET'],
        'specialist cannot open the admin panel' => ['specialist', 'admin.home', 'GET'],
        'specialist cannot open the operator panel' => ['specialist', 'operator.home', 'GET'],
        'specialist cannot open the audit log' => ['specialist', 'admin.audit.index', 'GET'],
        'specialist cannot open payments' => ['specialist', 'admin.payments.index', 'GET'],
        'specialist cannot edit SMS templates' => ['specialist', 'admin.sms.templates', 'GET'],
    ];
}

test('a role without the grant is refused the page (403 or 404), never shown it', function (string $role, string $route, string $method) {
    $user = User::factory()->role(StaffRole::from($role))->create(['is_active' => true, 'must_change_password' => false]);

    $response = $this->actingAs($user)->call($method, route($route));

    expect($response->getStatusCode())->toBeIn([403, 404], "{$role} reached {$route}");
})->with(forbiddenPages());
