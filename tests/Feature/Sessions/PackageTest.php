<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\DeviceSessionStatus;
use App\Enums\ServiceKind;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DeviceSession;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\User;
use App\Support\Jalali;

/**
 * An active device-session service, the only kind that can be sold as a package.
 */
function packageDeviceService(): Service
{
    return Service::factory()->create([
        'kind' => ServiceKind::DeviceSession,
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
}

function packageStaff(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

test('remaining sessions are the total minus the done sessions, and cancelled or missed ones do not count', function () {
    $package = SessionPackage::factory()->create(['total_sessions' => 10]);
    $common = ['client_id' => $package->client_id, 'package_id' => $package->getKey()];

    DeviceSession::factory()->count(3)->create($common);
    expect($package->remainingSessions())->toBe(7);

    DeviceSession::factory()->count(2)->create($common + ['status' => DeviceSessionStatus::Cancelled]);
    DeviceSession::factory()->create($common + ['status' => DeviceSessionStatus::Missed]);

    expect($package->remainingSessions())->toBe(7)
        ->and($package->doneSessions())->toBe(3);
});

test('the package list reads done counts in one query, and shows the remaining sessions', function () {
    $package = SessionPackage::factory()->create(['total_sessions' => 10]);
    DeviceSession::factory()->count(4)->create(['client_id' => $package->client_id, 'package_id' => $package->getKey()]);

    $response = $this->actingAs(packageStaff(StaffRole::Secretary))->get(route('admin.packages.index'));

    $response->assertOk()->assertSee('۶');
});

test('a package is never deleted', function () {
    $package = SessionPackage::factory()->create();

    expect(fn () => $package->delete())->toThrow(LogicException::class);
    expect(SessionPackage::query()->whereKey($package->getKey())->exists())->toBeTrue();
});

test('only admin and secretary can open the package pages', function (string $role, int $status) {
    $staff = packageStaff(StaffRole::from($role));

    $this->actingAs($staff)->get(route('admin.packages.index'))->assertStatus($status);
    $this->actingAs($staff)->get(route('admin.packages.create'))->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'secretary' => ['secretary', 200],
    'operator' => ['operator', 403],
    'specialist' => ['specialist', 403],
]);

test('only admin and secretary can save a package; the others are refused and nothing is saved', function (string $role, int $status) {
    $client = Client::factory()->create();
    $service = packageDeviceService();

    $this->actingAs(packageStaff(StaffRole::from($role)))
        ->post(route('admin.packages.store'), [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'total_sessions' => '10',
            'purchased_at' => '۱۴۰۵/۰۸/۲۶',
        ])
        ->assertStatus($status);

    expect(SessionPackage::query()->count())->toBe($status === 302 ? 1 : 0);
})->with([
    'admin' => ['admin', 302],
    'secretary' => ['secretary', 302],
    'operator' => ['operator', 403],
    'specialist' => ['specialist', 403],
]);

test('a guest is sent to log in', function () {
    $this->get(route('admin.packages.index'))->assertRedirect();
    $this->post(route('admin.packages.store'), [])->assertRedirect();
});

test('a package is saved with the Jalali date stored as Gregorian, Persian digits accepted, and audited', function () {
    $client = Client::factory()->create();
    $service = packageDeviceService();
    $secretary = packageStaff(StaffRole::Secretary);
    [$gy, $gm, $gd] = Jalali::toGregorian(1405, 8, 26);

    $this->actingAs($secretary)
        ->post(route('admin.packages.store'), [
            'client_id' => $client->getKey(),
            'service_id' => $service->getKey(),
            'total_sessions' => '۸',
            'price' => '۲٬۴۰۰٬۰۰۰',
            'purchased_at' => '۱۴۰۵/۰۸/۲۶',
        ])
        ->assertRedirect(route('admin.packages.index', ['client' => $client->getKey()]))
        ->assertSessionHas('success');

    $package = SessionPackage::query()->firstOrFail();

    expect($package->totalSessions())->toBe(8)
        ->and($package->price)->toBe(2400000)
        ->and($package->purchased_at->format('Y-m-d'))->toBe(sprintf('%04d-%02d-%02d', $gy, $gm, $gd))
        ->and($package->getAttribute('created_by'))->toBe($secretary->getKey());

    $audit = AuditLog::query()
        ->where('action', AuditAction::Create->value)
        ->where('subject_type', SessionPackage::class)
        ->where('subject_id', $package->getKey())
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->client_id)->toBe($client->getKey());
});

test('a package is refused when its data is wrong', function (array $overrides, string $field) {
    $client = Client::factory()->create();
    $service = packageDeviceService();

    $data = array_merge([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'total_sessions' => '10',
        'purchased_at' => '۱۴۰۵/۰۸/۲۶',
    ], $overrides);

    $this->actingAs(packageStaff(StaffRole::Admin))
        ->post(route('admin.packages.store'), $data)
        ->assertSessionHasErrors($field);

    expect(SessionPackage::query()->count())->toBe(0);
})->with([
    'no sessions' => [['total_sessions' => '0'], 'total_sessions'],
    'too many sessions' => [['total_sessions' => '999'], 'total_sessions'],
    'negative price' => [['price' => '-5'], 'price'],
    'not a date' => [['purchased_at' => 'بیست'], 'purchased_at'],
]);

test('a consultation is not sold as a package', function () {
    $client = Client::factory()->create();
    $consultation = Service::factory()->create(['kind' => ServiceKind::Consultation, 'is_active' => true]);

    $this->actingAs(packageStaff(StaffRole::Admin))
        ->post(route('admin.packages.store'), [
            'client_id' => $client->getKey(),
            'service_id' => $consultation->getKey(),
            'total_sessions' => '10',
            'purchased_at' => '۱۴۰۵/۰۸/۲۶',
        ])
        ->assertSessionHasErrors('service_id');

    expect(SessionPackage::query()->count())->toBe(0);
});

test('a deleted client cannot be given a package', function () {
    $client = Client::factory()->create();
    $client->delete();

    $this->actingAs(packageStaff(StaffRole::Admin))
        ->post(route('admin.packages.store'), [
            'client_id' => $client->getKey(),
            'service_id' => packageDeviceService()->getKey(),
            'total_sessions' => '10',
            'purchased_at' => '۱۴۰۵/۰۸/۲۶',
        ])
        ->assertSessionHasErrors('client_id');

    expect(SessionPackage::query()->count())->toBe(0);
});

test('the package list can be filtered by client, and the client file links to it', function () {
    $ali = Client::factory()->create(['first_name' => 'علی', 'last_name' => 'رضایی']);
    $sara = Client::factory()->create(['first_name' => 'سارا', 'last_name' => 'کریمی']);
    $service = packageDeviceService();
    SessionPackage::factory()->create(['client_id' => $ali->getKey(), 'service_id' => $service->getKey()]);
    SessionPackage::factory()->create(['client_id' => $sara->getKey(), 'service_id' => $service->getKey()]);

    $staff = packageStaff(StaffRole::Admin);

    $this->actingAs($staff)->get(route('admin.packages.index', ['client' => $ali->getKey()]))
        ->assertOk()
        ->assertSee('علی')
        ->assertDontSee('سارا');

    $this->actingAs($staff)->get(route('admin.clients.show', $ali))
        ->assertOk()
        ->assertSee(route('admin.packages.index', ['client' => $ali->getKey()]), false);
});
