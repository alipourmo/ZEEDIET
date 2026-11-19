<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ScheduleSeeder;
use Database\Seeders\StaffSeeder;

/**
 * A full week form: Saturday to Thursday on, Friday off. $overrides replace single fields.
 *
 * @param  array<int, array<string, string>>  $overrides
 * @return array<string, mixed>
 */
function weekPayload(array $overrides = []): array
{
    $days = [];
    for ($day = 0; $day < 7; $day++) {
        $days[$day] = $day < 6 ? ['enabled' => '1', 'start' => '09:00', 'end' => '20:00'] : [];
    }

    foreach ($overrides as $day => $values) {
        $days[$day] = $values;
    }

    return ['days' => $days];
}

test('only the admin reaches the schedule and holiday screens', function () {
    $device = Device::factory()->create();

    foreach (['secretary' => 403, 'operator' => 403, 'specialist' => 403, 'admin' => 200] as $role => $status) {
        $user = User::factory()->role(StaffRole::from($role))->create();

        $this->actingAs($user)->get('/admin/schedules')->assertStatus($status);
        $this->actingAs($user)->get("/admin/schedules/device/{$device->getKey()}/edit")->assertStatus($status);
        $this->actingAs($user)->get('/admin/holidays')->assertStatus($status);
        $this->actingAs($user)->get('/admin/holidays/create')->assertStatus($status);
    }
});

test('a non-admin cannot change hours or holidays', function () {
    $secretary = User::factory()->role(StaffRole::Secretary)->create();
    $device = Device::factory()->create();

    $this->actingAs($secretary)->put("/admin/schedules/device/{$device->getKey()}", weekPayload())->assertForbidden();
    $this->actingAs($secretary)->post('/admin/holidays', ['date' => '۱۴۰۵/۰۱/۰۱', 'title' => 'x'])->assertForbidden();

    expect(Schedule::query()->count())->toBe(0);
    expect(Holiday::query()->count())->toBe(0);
});

test('the admin saves a week: enabled days are stored, switched-off days are not', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $room = Room::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/schedules/room/{$room->getKey()}", weekPayload([1 => ['enabled' => '1', 'start' => '10:00', 'end' => '18:30']]))
        ->assertRedirect(route('admin.schedules.index'));

    $rows = Schedule::query()->where('resource_type', 'room')->where('resource_id', $room->getKey())
        ->orderBy('weekday')->get();

    expect($rows)->toHaveCount(6);
    expect($rows->pluck('weekday')->all())->toBe([0, 1, 2, 3, 4, 5]);
    expect((string) $rows[1]->start_time)->toBe('10:00:00');
    expect((string) $rows[1]->end_time)->toBe('18:30:00');
});

test('saving again replaces the whole week rather than adding rows', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $device = Device::factory()->create();

    $this->actingAs($admin)->put("/admin/schedules/device/{$device->getKey()}", weekPayload());
    $this->actingAs($admin)->put("/admin/schedules/device/{$device->getKey()}", weekPayload([0 => ['enabled' => '1', 'start' => '08:00', 'end' => '12:00']]));

    expect(Schedule::query()->where('resource_id', $device->getKey())->count())->toBe(1);
});

test('an end time before the start time is refused with a message on that day', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $device = Device::factory()->create();

    $this->actingAs($admin)
        ->from("/admin/schedules/device/{$device->getKey()}/edit")
        ->put("/admin/schedules/device/{$device->getKey()}", weekPayload([2 => ['enabled' => '1', 'start' => '20:00', 'end' => '09:00']]))
        ->assertSessionHasErrors(['days.2.end' => __('scheduling.end_after_start')]);

    expect(Schedule::query()->count())->toBe(0);
});

test('a day ticked without both times is refused', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $device = Device::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/schedules/device/{$device->getKey()}", weekPayload([3 => ['enabled' => '1', 'start' => '09:00', 'end' => '']]))
        ->assertSessionHasErrors(['days.3.end']);
});

test('the schedule of a non-specialist user cannot be edited, and an unknown resource is a 404', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $secretary = User::factory()->role(StaffRole::Secretary)->create();

    $this->actingAs($admin)->get("/admin/schedules/user/{$secretary->getKey()}/edit")->assertNotFound();
    $this->actingAs($admin)->get('/admin/schedules/device/999999/edit')->assertNotFound();
    $this->actingAs($admin)->get('/admin/schedules/unicorn/1/edit')->assertNotFound();
});

test('the form shows the saved days after a successful save', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $device = Device::factory()->create();

    $this->actingAs($admin)->put("/admin/schedules/device/{$device->getKey()}", weekPayload());

    $this->actingAs($admin)->get("/admin/schedules/device/{$device->getKey()}/edit")
        ->assertOk()
        ->assertSee('value="09:00"', false)
        ->assertSee('value="20:00"', false);
});

test('a holiday is entered in Jalali and stored as Gregorian', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/holidays', ['date' => '۱۴۰۵/۰۱/۰۱', 'title' => 'نوروز'])
        ->assertRedirect(route('admin.holidays.index'));

    expect(Holiday::query()->where('title', 'نوروز')->value('date')->format('Y-m-d'))->toBe('2026-03-21');
});

test('the same holiday date cannot be entered twice, but an edit may keep its own date', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $holiday = Holiday::query()->create(['date' => '2026-03-21', 'title' => 'نوروز']);

    $this->actingAs($admin)->post('/admin/holidays', ['date' => '۱۴۰۵/۰۱/۰۱', 'title' => 'دوباره'])
        ->assertSessionHasErrors('date');

    $this->actingAs($admin)->put("/admin/holidays/{$holiday->getKey()}", ['date' => '۱۴۰۵/۰۱/۰۱', 'title' => 'نوروز ۱۴۰۵'])
        ->assertSessionHasNoErrors();
});

test('an invalid Jalali date is refused', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->post('/admin/holidays', ['date' => '۱۴۰۵/۱۲/۳۱', 'title' => 'x'])
        ->assertSessionHasErrors('date');
});

test('an admin deletes a holiday', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $holiday = Holiday::query()->create(['date' => '2026-03-21', 'title' => 'نوروز']);

    $this->actingAs($admin)->delete("/admin/holidays/{$holiday->getKey()}")->assertRedirect(route('admin.holidays.index'));

    expect(Holiday::query()->whereKey($holiday->getKey())->exists())->toBeFalse();
});

test('the seeder gives every catalog resource Saturday to Thursday hours, and runs twice without change', function () {
    $this->seed([StaffSeeder::class, CatalogSeeder::class, ScheduleSeeder::class]);
    $first = Schedule::query()->count();

    $this->seed([StaffSeeder::class, CatalogSeeder::class, ScheduleSeeder::class]);

    expect(Schedule::query()->count())->toBe($first);

    $device = Device::query()->firstOrFail();
    $days = Schedule::query()->where('resource_id', $device->getKey())->where('resource_type', 'device')->pluck('weekday')->sort()->values()->all();
    expect($days)->toBe([0, 1, 2, 3, 4, 5]);
});
