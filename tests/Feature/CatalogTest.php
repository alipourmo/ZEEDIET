<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Device;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

/** A valid service form body. Persian digits and the price separator are typed on purpose. */
function catalogServicePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'مشاوره تست',
        'kind' => 'consultation',
        'default_duration_min' => '۳۰',
        'requires_device' => null,
        'requires_specialist' => '1',
        'default_price' => '',
        'is_active' => '1',
    ], $overrides);
}

test('only the admin reaches the catalog', function () {
    foreach (['admin' => 200, 'secretary' => 403, 'operator' => 403, 'specialist' => 403] as $role => $status) {
        $user = User::factory()->role(StaffRole::from($role))->create();

        foreach (['/admin/services', '/admin/devices', '/admin/rooms', '/admin/services/create', '/admin/devices/create', '/admin/rooms/create'] as $uri) {
            $this->actingAs($user)->get($uri)->assertStatus($status);
        }
    }
});

test('a non-admin cannot save to the catalog', function () {
    $secretary = User::factory()->role(StaffRole::Secretary)->create();

    $this->actingAs($secretary)->post(route('admin.services.store'), catalogServicePayload())->assertForbidden();
    $this->actingAs($secretary)->post(route('admin.devices.store'), ['name' => 'x'])->assertForbidden();
    $this->actingAs($secretary)->post(route('admin.rooms.store'), ['name' => 'x'])->assertForbidden();

    expect(Service::count() + Device::count() + Room::count())->toBe(0);
});

test('the admin creates a service, with Persian digits and a price typed with separators', function () {
    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->post(route('admin.services.store'), catalogServicePayload(['default_price' => '۱۵۰,۰۰۰']))
        ->assertRedirect(route('admin.services.index'));

    $service = Service::query()->where('name', 'مشاوره تست')->firstOrFail();

    expect($service->default_duration_min)->toBe(30);
    expect($service->default_price)->toBe(150000);
    expect($service->requires_specialist)->toBeTrue();
    expect($service->requires_device)->toBeFalse();
    expect($service->kind->value)->toBe('consultation');
});

test('an empty price is stored as not set', function () {
    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->post(route('admin.services.store'), catalogServicePayload(['default_price' => '  ']));

    expect(Service::query()->value('default_price'))->toBeNull();
});

test('service validation rejects an unknown kind, an out-of-range duration and a duplicate name', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->post(route('admin.services.store'), catalogServicePayload(['kind' => 'unicorn']))
        ->assertSessionHasErrors('kind');

    $this->actingAs($admin)->post(route('admin.services.store'), catalogServicePayload(['default_duration_min' => '۲']))
        ->assertSessionHasErrors('default_duration_min');

    $this->actingAs($admin)->post(route('admin.services.store'), catalogServicePayload());
    $this->actingAs($admin)->post(route('admin.services.store'), catalogServicePayload())
        ->assertSessionHasErrors('name');
});

test('editing a service keeps its own name without a duplicate error', function () {
    $service = Service::factory()->create(['name' => 'آنالیز بدن']);

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->put(route('admin.services.update', $service), catalogServicePayload(['name' => 'آنالیز بدن', 'default_duration_min' => '25']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.services.index'));

    expect($service->refresh()->default_duration_min)->toBe(25);
});

test('unticking "active" deactivates a service, which is kept', function () {
    $service = Service::factory()->create(['is_active' => true]);

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->put(route('admin.services.update', $service), catalogServicePayload(['name' => $service->name, 'is_active' => null]));

    expect($service->refresh()->is_active)->toBeFalse();
    expect(Service::query()->whereKey($service->getKey())->exists())->toBeTrue();
});

test('the catalog has no delete route: a delete request is refused', function () {
    $service = Service::factory()->create();

    $this->actingAs(User::factory()->role(StaffRole::Admin)->create())
        ->delete("/admin/services/{$service->getKey()}")
        ->assertStatus(405);

    expect(Service::query()->whereKey($service->getKey())->exists())->toBeTrue();
});

test('a device stores its contraindications once each, and rejects unknown keys', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->post(route('admin.devices.store'), [
        'name' => 'دستگاه تست',
        'model' => 'X-1',
        'contraindications' => ['pacemaker', 'pacemaker', 'epilepsy'],
        'is_active' => '1',
    ])->assertRedirect(route('admin.devices.index'));

    expect(Device::query()->where('name', 'دستگاه تست')->value('contraindications'))->toBe(['pacemaker', 'epilepsy']);

    $this->actingAs($admin)->post(route('admin.devices.store'), [
        'name' => 'دستگاه دیگر',
        'contraindications' => ['unicorn'],
    ])->assertSessionHasErrors('contraindications.0');
});

test('the admin creates and renames a room, and a duplicate room name is refused', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->post(route('admin.rooms.store'), ['name' => 'اتاق یک', 'is_active' => '1'])
        ->assertRedirect(route('admin.rooms.index'));

    $room = Room::query()->where('name', 'اتاق یک')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.rooms.update', $room), ['name' => 'اتاق دو', 'is_active' => '1'])
        ->assertRedirect(route('admin.rooms.index'));

    expect($room->refresh()->name)->toBe('اتاق دو');

    Room::factory()->create(['name' => 'اتاق سه']);
    $this->actingAs($admin)->post(route('admin.rooms.store'), ['name' => 'اتاق سه'])->assertSessionHasErrors('name');
});

test('the example seeder creates the sample catalog, and running it again adds nothing', function () {
    $this->seed(CatalogSeeder::class);
    $this->seed(CatalogSeeder::class);

    expect(Service::count())->toBe(3);
    expect(Device::count())->toBe(2);
    expect(Room::count())->toBe(2);

    $slimming = Service::query()->where('name', 'جلسه دستگاه لاغری')->firstOrFail();
    expect($slimming->requires_device)->toBeTrue();
    expect($slimming->default_duration_min)->toBe(45);
    expect($slimming->default_price)->toBeNull();
});

test('after a failed save, an unticked checkbox stays unticked', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    // The name is empty, so the save fails. requires_device was not ticked.
    $this->actingAs($admin)
        ->from(route('admin.services.create'))
        ->post(route('admin.services.store'), catalogServicePayload(['name' => '', 'requires_specialist' => '1']))
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)->get(route('admin.services.create'))
        ->assertDontSee('name="requires_device" value="1" checked', false)
        ->assertSee('name="requires_specialist" value="1" checked', false);
});
