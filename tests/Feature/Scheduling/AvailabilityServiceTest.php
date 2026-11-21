<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Scheduling\AvailabilityService;
use Carbon\CarbonImmutable;

// 2026-11-21 is a Saturday (weekday 0). 2026-11-20 is a Friday (weekday 6).

/**
 * Working hours for a resource on Saturday to Thursday, as the seeder gives them.
 */
function workWeek(ScheduleResource $type, int $id, string $start = '09:00', string $end = '20:00'): void
{
    foreach ([0, 1, 2, 3, 4, 5] as $weekday) {
        Schedule::query()->create([
            'resource_type' => $type->value,
            'resource_id' => $id,
            'weekday' => $weekday,
            'start_time' => $start.':00',
            'end_time' => $end.':00',
        ]);
    }
}

function tehran(string $date, string $time): CarbonImmutable
{
    return CarbonImmutable::parse("$date $time", 'Asia/Tehran');
}

/**
 * An appointment on the device for the given interval, for a client who is not otherwise busy.
 */
function bookDevice(Device $device, string $date, string $start, string $end, AppointmentStatus $status = AppointmentStatus::Scheduled): Appointment
{
    return Appointment::factory()->create([
        'client_id' => Client::factory(),
        'device_id' => $device->getKey(),
        'starts_at' => tehran($date, $start),
        'ends_at' => tehran($date, $end),
        'status' => $status,
    ]);
}

beforeEach(function () {
    $this->availability = app(AvailabilityService::class);
    $this->device = Device::factory()->create();
    workWeek(ScheduleResource::Device, $this->device->getKey());
});

test('Saturday is a working day, Friday is not', function () {
    expect($this->availability->isWorkingDay(tehran('2026-11-21', '10:00')))->toBeTrue();
    expect($this->availability->isWorkingDay(tehran('2026-11-20', '10:00')))->toBeFalse();
});

test('a holiday is not a working day, even on a Saturday', function () {
    Holiday::query()->create(['date' => '2026-11-21', 'title' => 'تعطیل تست']);

    expect($this->availability->isWorkingDay(tehran('2026-11-21', '10:00')))->toBeFalse();
    expect($this->availability->isFree(ScheduleResource::Device, $this->device->getKey(), tehran('2026-11-21', '10:00'), tehran('2026-11-21', '10:30')))->toBeFalse();
});

test('a slot inside the working hours is inside the schedule, including its edges', function () {
    $id = $this->device->getKey();

    expect($this->availability->isWithinSchedule(ScheduleResource::Device, $id, tehran('2026-11-21', '09:00'), tehran('2026-11-21', '09:30')))->toBeTrue();
    expect($this->availability->isWithinSchedule(ScheduleResource::Device, $id, tehran('2026-11-21', '19:30'), tehran('2026-11-21', '20:00')))->toBeTrue();
});

test('a slot that starts before opening or ends after closing is outside the schedule', function () {
    $id = $this->device->getKey();

    expect($this->availability->isWithinSchedule(ScheduleResource::Device, $id, tehran('2026-11-21', '08:30'), tehran('2026-11-21', '09:30')))->toBeFalse();
    expect($this->availability->isWithinSchedule(ScheduleResource::Device, $id, tehran('2026-11-21', '19:30'), tehran('2026-11-21', '20:30')))->toBeFalse();
});

test('an interval that crosses midnight is never inside a schedule', function () {
    $id = $this->device->getKey();

    expect($this->availability->isWithinSchedule(ScheduleResource::Device, $id, tehran('2026-11-21', '19:30'), tehran('2026-11-22', '00:30')))->toBeFalse();
});

test('a resource with no hours on that weekday is unavailable', function () {
    // Friday has no rows for the device.
    expect($this->availability->isWithinSchedule(ScheduleResource::Device, $this->device->getKey(), tehran('2026-11-20', '10:00'), tehran('2026-11-20', '10:30')))->toBeFalse();

    // A room with no rows at all.
    $room = Room::factory()->create();
    expect($this->availability->isFree(ScheduleResource::Room, $room->getKey(), tehran('2026-11-21', '10:00'), tehran('2026-11-21', '10:30')))->toBeFalse();
});

test('a free slot is free', function () {
    expect($this->availability->isFree(ScheduleResource::Device, $this->device->getKey(), tehran('2026-11-21', '10:00'), tehran('2026-11-21', '10:30')))->toBeTrue();
});

test('an overlapping appointment makes the slot busy, a touching one does not', function () {
    bookDevice($this->device, '2026-11-21', '10:00', '10:30');
    $id = $this->device->getKey();

    // Overlaps by one minute.
    expect($this->availability->isFree(ScheduleResource::Device, $id, tehran('2026-11-21', '10:29'), tehran('2026-11-21', '11:00')))->toBeFalse();
    // Starts exactly when the other ends: allowed.
    expect($this->availability->isFree(ScheduleResource::Device, $id, tehran('2026-11-21', '10:30'), tehran('2026-11-21', '11:00')))->toBeTrue();
    // Ends exactly when the other starts: allowed.
    expect($this->availability->isFree(ScheduleResource::Device, $id, tehran('2026-11-21', '09:30'), tehran('2026-11-21', '10:00')))->toBeTrue();
});

test('cancelled and no-show appointments do not block the slot, other statuses do', function () {
    $id = $this->device->getKey();
    $slotStart = tehran('2026-11-21', '10:00');
    $slotEnd = tehran('2026-11-21', '10:30');

    bookDevice($this->device, '2026-11-21', '10:00', '10:30', AppointmentStatus::Cancelled);
    bookDevice($this->device, '2026-11-21', '10:00', '10:30', AppointmentStatus::NoShow);
    expect($this->availability->isFree(ScheduleResource::Device, $id, $slotStart, $slotEnd))->toBeTrue();

    bookDevice($this->device, '2026-11-21', '10:00', '10:30', AppointmentStatus::InProgress);
    expect($this->availability->isFree(ScheduleResource::Device, $id, $slotStart, $slotEnd))->toBeFalse();
});

test('an appointment can be checked against itself when it is moved', function () {
    $own = bookDevice($this->device, '2026-11-21', '10:00', '10:30');
    $id = $this->device->getKey();

    expect($this->availability->isFree(ScheduleResource::Device, $id, tehran('2026-11-21', '10:00'), tehran('2026-11-21', '10:30')))->toBeFalse();
    expect($this->availability->isFree(ScheduleResource::Device, $id, tehran('2026-11-21', '10:00'), tehran('2026-11-21', '10:30'), $own->getKey()))->toBeTrue();
});

test('a client with another appointment at the same time is busy', function () {
    $client = Client::factory()->create();
    Appointment::factory()->create([
        'client_id' => $client->getKey(),
        'starts_at' => tehran('2026-11-21', '11:00'),
        'ends_at' => tehran('2026-11-21', '11:30'),
    ]);

    expect($this->availability->clientIsBusy($client->getKey(), tehran('2026-11-21', '11:15'), tehran('2026-11-21', '11:45')))->toBeTrue();
    expect($this->availability->clientIsBusy($client->getKey(), tehran('2026-11-21', '11:30'), tehran('2026-11-21', '12:00')))->toBeFalse();
    expect($this->availability->clientIsBusy(Client::factory()->create()->getKey(), tehran('2026-11-21', '11:15'), tehran('2026-11-21', '11:45')))->toBeFalse();
});

test('problems() names the field that is at fault', function () {
    $specialist = User::factory()->role(StaffRole::Specialist)->create();
    $room = Room::factory()->create();
    workWeek(ScheduleResource::User, $specialist->getKey());
    workWeek(ScheduleResource::Room, $room->getKey());
    bookDevice($this->device, '2026-11-21', '10:00', '10:30');

    $client = Client::factory()->create();
    $start = tehran('2026-11-21', '10:00');
    $end = tehran('2026-11-21', '10:30');

    // Device busy.
    expect($this->availability->problems($start, $end, $client->getKey(), $specialist->getKey(), $this->device->getKey(), $room->getKey()))
        ->toBe(['device_id' => __('scheduling.problem_resource_busy')]);

    // Specialist outside hours.
    expect($this->availability->problems(tehran('2026-11-21', '08:00'), tehran('2026-11-21', '08:30'), $client->getKey(), $specialist->getKey(), null, null))
        ->toBe(['specialist_id' => __('scheduling.problem_outside_hours')]);

    // Friday.
    expect($this->availability->problems(tehran('2026-11-20', '10:00'), tehran('2026-11-20', '10:30'), $client->getKey(), null, null, null))
        ->toBe(['starts_at' => __('scheduling.problem_closed_day')]);

    // End before start.
    expect($this->availability->problems($end, $start, $client->getKey(), null, null, null))
        ->toBe(['ends_at' => __('scheduling.problem_end_before_start')]);

    // Free slot: no problems.
    expect($this->availability->problems(tehran('2026-11-21', '12:00'), tehran('2026-11-21', '12:30'), $client->getKey(), $specialist->getKey(), $this->device->getKey(), $room->getKey()))
        ->toBe([]);
});

test('problems() reports a busy client on the client field', function () {
    $client = Client::factory()->create();
    Appointment::factory()->create([
        'client_id' => $client->getKey(),
        'starts_at' => tehran('2026-11-21', '12:00'),
        'ends_at' => tehran('2026-11-21', '12:30'),
    ]);

    expect($this->availability->problems(tehran('2026-11-21', '12:00'), tehran('2026-11-21', '12:30'), $client->getKey(), null, null, null))
        ->toBe(['client_id' => __('scheduling.problem_client_busy')]);
});
