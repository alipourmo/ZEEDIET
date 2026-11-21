<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Device;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;

// 2027-01-01 is a Friday. 2027-01-02 is a Saturday.

/**
 * A client, a specialist, a device, a room and a 30-minute service that needs all three. The specialist, device and
 * room are open Saturday to Thursday 09:00 to 20:00.
 *
 * @return array{secretary: User, client: Client, specialist: User, device: Device, room: Room, service: Service}
 */
function formWorld(): array
{
    $specialist = User::factory()->role(StaffRole::Specialist)->create(['is_active' => true]);
    $device = Device::factory()->create();
    $room = Room::factory()->create();

    $owners = [
        [ScheduleResource::User, $specialist],
        [ScheduleResource::Device, $device],
        [ScheduleResource::Room, $room],
    ];

    foreach ([0, 1, 2, 3, 4, 5] as $weekday) {
        foreach ($owners as [$type, $owner]) {
            Schedule::query()->create([
                'resource_type' => $type->value,
                'resource_id' => $owner->getKey(),
                'weekday' => $weekday,
                'start_time' => '09:00:00',
                'end_time' => '20:00:00',
            ]);
        }
    }

    return [
        'secretary' => User::factory()->role(StaffRole::Secretary)->create(['is_active' => true]),
        'client' => Client::factory()->create(),
        'specialist' => $specialist,
        'device' => $device,
        'room' => $room,
        'service' => Service::factory()->create([
            'default_duration_min' => 30,
            'requires_device' => true,
            'requires_specialist' => true,
            'is_active' => true,
        ]),
    ];
}

/**
 * @param  array{secretary: User, client: Client, specialist: User, device: Device, room: Room, service: Service}  $world
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bookingForm(array $world, array $overrides = []): array
{
    return array_merge([
        'client_id' => $world['client']->getKey(),
        'service_id' => $world['service']->getKey(),
        'specialist_id' => $world['specialist']->getKey(),
        'device_id' => $world['device']->getKey(),
        'room_id' => $world['room']->getKey(),
        'starts_at' => '2027-01-02 10:00',
        'note' => '',
    ], $overrides);
}

test('admin and secretary may open the booking form, nobody else', function (StaffRole $role, int $status) {
    $user = User::factory()->role($role)->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('admin.appointments.create'))
        ->assertStatus($status);
})->with([
    [StaffRole::Admin, 200],
    [StaffRole::Secretary, 200],
    [StaffRole::Operator, 403],
    [StaffRole::Specialist, 403],
]);

test('only admin and secretary may book', function (StaffRole $role, int $status) {
    $world = formWorld();
    $user = User::factory()->role($role)->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(route('admin.appointments.store'), bookingForm($world))
        ->assertStatus($status);
})->with([
    [StaffRole::Admin, 302],
    [StaffRole::Secretary, 302],
    [StaffRole::Operator, 403],
    [StaffRole::Specialist, 403],
]);

test('a guest is sent to sign in', function () {
    $this->get(route('admin.appointments.create'))
        ->assertRedirect(route('login'));
});

test('the form books an appointment that ends after the service length', function () {
    $world = formWorld();
    $history = AppointmentStatusHistory::query()->count();

    $response = $this->actingAs($world['secretary'])
        ->post(route('admin.appointments.store'), bookingForm($world));

    $appointment = Appointment::query()->sole();

    $response->assertRedirect(route('admin.appointments.edit', $appointment));

    expect($appointment->time('starts_at')->format('Y-m-d H:i'))->toBe('2027-01-02 10:00')
        ->and($appointment->time('ends_at')->format('Y-m-d H:i'))->toBe('2027-01-02 10:30')
        ->and($appointment->currentStatus())->toBe(AppointmentStatus::Scheduled)
        ->and(AppointmentStatusHistory::query()->count())->toBe($history + 1)
        ->and(AuditLog::query()->where('subject_type', Appointment::class)->count())->toBe(1);
});

test('a second booking of the same device and specialist time is refused', function () {
    $world = formWorld();

    $this->actingAs($world['secretary'])
        ->post(route('admin.appointments.store'), bookingForm($world))
        ->assertRedirect();

    $this->actingAs($world['secretary'])
        ->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), bookingForm($world, ['starts_at' => '2027-01-02 10:15']))
        ->assertSessionHasErrors(['device_id', 'specialist_id']);

    expect(Appointment::query()->count())->toBe(1);
});

test('a Friday is refused on the start time', function () {
    $world = formWorld();

    $this->actingAs($world['secretary'])
        ->from(route('admin.appointments.create'))
        ->post(route('admin.appointments.store'), bookingForm($world, ['starts_at' => '2027-01-01 10:00']))
        ->assertSessionHasErrors(['starts_at']);

    expect(Appointment::query()->count())->toBe(0);
});

test('rescheduling changes only the times, and re-runs the checks', function () {
    $world = formWorld();

    $this->actingAs($world['secretary'])
        ->post(route('admin.appointments.store'), bookingForm($world));

    $appointment = Appointment::query()->sole();
    $history = AppointmentStatusHistory::query()->count();

    $this->actingAs($world['secretary'])
        ->put(route('admin.appointments.update', $appointment), bookingForm($world, ['starts_at' => '2027-01-02 12:00']))
        ->assertRedirect(route('admin.appointments.edit', $appointment));

    $appointment->refresh();

    expect($appointment->time('starts_at')->format('H:i'))->toBe('12:00')
        ->and($appointment->time('ends_at')->format('H:i'))->toBe('12:30')
        ->and(AppointmentStatusHistory::query()->count())->toBe($history)
        ->and(AuditLog::query()->where('action', 'update')->where('subject_id', $appointment->getKey())->exists())->toBeTrue();
});

test('a reschedule into a busy time is refused and keeps the old time', function () {
    $world = formWorld();
    $start = fn (string $time): array => bookingForm($world, ['starts_at' => '2027-01-02 '.$time]);

    $this->actingAs($world['secretary'])->post(route('admin.appointments.store'), $start('10:00'));
    $this->actingAs($world['secretary'])->post(route('admin.appointments.store'), $start('13:00'));

    $moving = Appointment::query()->orderBy('starts_at')->firstOrFail();

    $this->actingAs($world['secretary'])
        ->from(route('admin.appointments.edit', $moving))
        ->put(route('admin.appointments.update', $moving), $start('13:00'))
        ->assertSessionHasErrors(['device_id']);

    $moving->refresh();

    expect($moving->time('starts_at')->format('H:i'))->toBe('10:00');
});

test('a cancelled appointment shows that it cannot be moved', function () {
    $world = formWorld();

    $appointment = Appointment::factory()->create([
        'client_id' => $world['client']->getKey(),
        'service_id' => $world['service']->getKey(),
        'device_id' => $world['device']->getKey(),
        'starts_at' => CarbonImmutable::parse('2027-01-02 10:00', 'Asia/Tehran'),
        'ends_at' => CarbonImmutable::parse('2027-01-02 10:30', 'Asia/Tehran'),
        'status' => AppointmentStatus::Cancelled,
    ]);

    $this->actingAs($world['secretary'])
        ->get(route('admin.appointments.edit', $appointment))
        ->assertOk()
        ->assertSee(__('appointments.not_reschedulable'))
        ->assertDontSee(__('appointments.submit_reschedule'));
});

test('a scheduled appointment can be edited', function () {
    $world = formWorld();

    $appointment = Appointment::factory()->create([
        'client_id' => $world['client']->getKey(),
        'service_id' => $world['service']->getKey(),
        'device_id' => $world['device']->getKey(),
        'starts_at' => CarbonImmutable::parse('2027-01-02 10:00', 'Asia/Tehran'),
        'ends_at' => CarbonImmutable::parse('2027-01-02 10:30', 'Asia/Tehran'),
        'status' => AppointmentStatus::Scheduled,
    ]);

    $this->actingAs($world['secretary'])
        ->get(route('admin.appointments.edit', $appointment))
        ->assertOk()
        ->assertSee(__('appointments.submit_reschedule'));
});
