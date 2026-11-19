<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Support\Jalali;
use Carbon\CarbonImmutable;

// 2027-01-01 is a Friday (weekday 6, weekend). 2027-01-02 is a Saturday (weekday 0, open).

/**
 * A device, open Saturday to Thursday 09:00 to 20:00, and a 30-minute service that needs it.
 *
 * @return array{secretary: User, device: Device, service: Service}
 */
function slotWorld(): array
{
    $device = Device::factory()->create();

    foreach ([0, 1, 2, 3, 4, 5] as $weekday) {
        Schedule::query()->create([
            'resource_type' => ScheduleResource::Device->value,
            'resource_id' => $device->getKey(),
            'weekday' => $weekday,
            'start_time' => '09:00:00',
            'end_time' => '20:00:00',
        ]);
    }

    return [
        'secretary' => User::factory()->role(StaffRole::Secretary)->create(['is_active' => true]),
        'device' => $device,
        'service' => Service::factory()->create([
            'default_duration_min' => 30,
            'requires_device' => true,
            'requires_specialist' => false,
            'is_active' => true,
        ]),
    ];
}

/**
 * @param  array{secretary: User, device: Device, service: Service}  $world
 * @param  array<string, int|string>  $overrides
 * @return array<string, int|string>
 */
function slotQuery(array $world, string $gregorianDay = '2027-01-02', array $overrides = []): array
{
    return array_merge([
        'service_id' => $world['service']->getKey(),
        'device_id' => $world['device']->getKey(),
        'from_jalali' => Jalali::formatGregorianAsJalali($gregorianDay),
        'days' => 1,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $day
 * @return list<string>
 */
function slotStarts(array $day): array
{
    return array_column($day['slots'], 'starts_at');
}

/**
 * @param  array{secretary: User, device: Device, service: Service}  $world
 */
function busyAt(array $world, string $start, string $end, AppointmentStatus $status = AppointmentStatus::Scheduled): Appointment
{
    return Appointment::factory()->create([
        'service_id' => $world['service']->getKey(),
        'device_id' => $world['device']->getKey(),
        'starts_at' => CarbonImmutable::parse($start, 'Asia/Tehran'),
        'ends_at' => CarbonImmutable::parse($end, 'Asia/Tehran'),
        'status' => $status,
    ]);
}

test('the picker offers open times, and a booking does not block the time it touches', function () {
    $world = slotWorld();
    busyAt($world, '2027-01-02 10:00:00', '2027-01-02 11:00:00');

    $day = $this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world)))
        ->assertOk()
        ->json('days.0');

    $starts = slotStarts($day);

    expect($day['date'])->toBe('2027-01-02')
        ->and($day['closed'])->toBeNull()
        ->and($starts)->toContain('2027-01-02 09:30')    // ends exactly when the booking starts
        ->and($starts)->toContain('2027-01-02 11:00')    // starts exactly when the booking ends
        ->and($starts)->toContain('2027-01-02 19:30')    // the last start that still ends at 20:00
        ->and($starts)->not->toContain('2027-01-02 09:45')
        ->and($starts)->not->toContain('2027-01-02 10:00')
        ->and($starts)->not->toContain('2027-01-02 10:30')
        ->and($starts)->not->toContain('2027-01-02 20:00');
});

test('a cancelled or no-show booking does not block its time', function () {
    $world = slotWorld();
    busyAt($world, '2027-01-02 14:00:00', '2027-01-02 14:30:00', AppointmentStatus::Cancelled);
    busyAt($world, '2027-01-02 15:00:00', '2027-01-02 15:30:00', AppointmentStatus::NoShow);

    $starts = slotStarts($this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world)))
        ->json('days.0'));

    expect($starts)->toContain('2027-01-02 14:00')
        ->and($starts)->toContain('2027-01-02 15:00');
});

test('a holiday and a Friday are closed, and the reason is given', function () {
    $world = slotWorld();
    Holiday::query()->create(['date' => '2027-01-02', 'title' => 'تعطیل آزمایشی']);

    $holiday = $this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world)))
        ->json('days.0');

    $friday = $this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world, '2027-01-01')))
        ->json('days.0');

    expect($holiday['closed'])->toBe('holiday')
        ->and($holiday['slots'])->toBe([])
        ->and($friday['closed'])->toBe('weekend')
        ->and($friday['slots'])->toBe([]);
});

test('times already past are not offered', function () {
    $world = slotWorld();
    $this->travelTo(CarbonImmutable::parse('2027-01-02 12:00:00', 'Asia/Tehran'));

    $starts = slotStarts($this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world)))
        ->json('days.0'));

    expect($starts)->not->toContain('2027-01-02 09:00')
        ->and($starts)->not->toContain('2027-01-02 11:45')
        ->and($starts)->toContain('2027-01-02 12:00');
});

test('the picker needs a resource, a valid Jalali date and at most 14 days', function () {
    $world = slotWorld();

    $noResource = slotQuery($world);
    unset($noResource['device_id']);

    $this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', $noResource))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['specialist_id']);

    $this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world, '2027-01-02', ['from_jalali' => '1405/13/40'])))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from_jalali']);

    $this->actingAs($world['secretary'])
        ->getJson(route('admin.api.availability', slotQuery($world, '2027-01-02', ['days' => 15])))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['days']);
});

test('only admin and secretary may read the picker', function (StaffRole $role, int $status) {
    $world = slotWorld();
    $user = User::factory()->role($role)->create(['is_active' => true]);

    $this->actingAs($user)
        ->getJson(route('admin.api.availability', slotQuery($world)))
        ->assertStatus($status);
})->with([
    [StaffRole::Admin, 200],
    [StaffRole::Secretary, 200],
    [StaffRole::Operator, 403],
    [StaffRole::Specialist, 403],
]);

test('a guest cannot read the picker', function () {
    $world = slotWorld();

    $this->getJson(route('admin.api.availability', slotQuery($world)))
        ->assertUnauthorized();
});
