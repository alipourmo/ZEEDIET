<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use App\Services\Scheduling\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

// 2026-11-21 is a Saturday. 2026-11-20 is a Friday.

function bookingWorkWeek(ScheduleResource $type, int $id): void
{
    foreach ([0, 1, 2, 3, 4, 5] as $weekday) {
        Schedule::query()->create([
            'resource_type' => $type->value,
            'resource_id' => $id,
            'weekday' => $weekday,
            'start_time' => '09:00:00',
            'end_time' => '20:00:00',
        ]);
    }
}

function clinicTime(string $date, string $time): CarbonImmutable
{
    return CarbonImmutable::parse("$date $time", 'Asia/Tehran');
}

/**
 * Booking input for a device session, from 10:00 to 10:30 on the given day by default.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bookingInput(object $ctx, array $overrides = []): array
{
    return array_merge([
        'client_id' => $ctx->client->getKey(),
        'service_id' => $ctx->service->getKey(),
        'specialist_id' => null,
        'device_id' => $ctx->device->getKey(),
        'room_id' => null,
        'starts_at' => clinicTime('2026-11-21', '10:00'),
        'ends_at' => clinicTime('2026-11-21', '10:30'),
        'note' => null,
    ], $overrides);
}

beforeEach(function () {
    $this->booking = app(BookingService::class);
    $this->admin = User::factory()->role(StaffRole::Admin)->create();
    $this->service = Service::factory()->create([
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
    $this->device = Device::factory()->create();
    bookingWorkWeek(ScheduleResource::Device, $this->device->getKey());
    $this->client = Client::factory()->create();
});

test('a booking is saved as scheduled, with a history row and an audit row', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled);
    expect($appointment->created_by)->toBe($this->admin->getKey());
    expect($appointment->source->value)->toBe('staff');

    $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->get();
    expect($history)->toHaveCount(1);
    expect($history[0]->from_status)->toBeNull();
    expect($history[0]->to_status)->toBe(AppointmentStatus::Scheduled);

    expect(AuditLog::query()->where('action', AuditAction::Create->value)->where('subject_id', $appointment->getKey())->exists())->toBeTrue();
});

test('a device already booked at that time refuses the booking, and writes nothing', function () {
    $this->booking->book(bookingInput($this), $this->admin);

    $other = Client::factory()->create();

    expect(fn () => $this->booking->book(bookingInput($this, ['client_id' => $other->getKey()]), $this->admin))
        ->toThrow(ValidationException::class);

    expect(Appointment::query()->count())->toBe(1);
    expect(AppointmentStatusHistory::query()->count())->toBe(1);
});

test('the refusal names the field at fault', function () {
    $this->booking->book(bookingInput($this), $this->admin);

    try {
        $this->booking->book(bookingInput($this, ['client_id' => Client::factory()->create()->getKey()]), $this->admin);
        $this->fail('expected a conflict');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('device_id');
        expect($e->errors()['device_id'][0])->toBe(__('scheduling.problem_resource_busy'));
    }
});

test('a client with another appointment at the same time cannot be booked again', function () {
    $this->booking->book(bookingInput($this), $this->admin);
    $otherDevice = Device::factory()->create();
    bookingWorkWeek(ScheduleResource::Device, $otherDevice->getKey());

    expect(fn () => $this->booking->book(bookingInput($this, ['device_id' => $otherDevice->getKey()]), $this->admin))
        ->toThrow(ValidationException::class);
});

test('appointments that touch, one ending as the next starts, are both allowed', function () {
    $this->booking->book(bookingInput($this), $this->admin);

    $next = $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '10:30'),
        'ends_at' => clinicTime('2026-11-21', '11:00'),
    ]), $this->admin);

    expect($next->exists)->toBeTrue();
});

test('a Friday or a holiday refuses the booking on the starts_at field', function () {
    expect(fn () => $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-20', '10:00'),
        'ends_at' => clinicTime('2026-11-20', '10:30'),
    ]), $this->admin))->toThrow(ValidationException::class);

    Holiday::query()->create(['date' => '2026-11-21', 'title' => 'تعطیل']);

    expect(fn () => $this->booking->book(bookingInput($this), $this->admin))->toThrow(ValidationException::class);
});

test('a time outside the device hours is refused', function () {
    expect(fn () => $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '08:30'),
        'ends_at' => clinicTime('2026-11-21', '09:00'),
    ]), $this->admin))->toThrow(ValidationException::class);
});

test('a service that needs a device cannot be booked without one', function () {
    try {
        $this->booking->book(bookingInput($this, ['device_id' => null]), $this->admin);
        $this->fail('expected a refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('device_id');
    }
});

test('a service that needs a specialist cannot be booked without one', function () {
    $this->service->update(['requires_specialist' => true]);

    try {
        $this->booking->book(bookingInput($this), $this->admin);
        $this->fail('expected a refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('specialist_id');
    }
});

test('an inactive service or an inactive device cannot be booked', function () {
    $this->service->update(['is_active' => false]);

    try {
        $this->booking->book(bookingInput($this), $this->admin);
        $this->fail('expected a refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('service_id');
    }

    $this->service->update(['is_active' => true]);
    $this->device->update(['is_active' => false]);

    try {
        $this->booking->book(bookingInput($this), $this->admin);
        $this->fail('expected a refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('device_id');
    }
});

test('an end time that is not after the start is refused', function () {
    expect(fn () => $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '10:30'),
        'ends_at' => clinicTime('2026-11-21', '10:00'),
    ]), $this->admin))->toThrow(ValidationException::class);
});

test('rescheduling re-checks the slot, ignores the appointment itself, and writes an audit row with old and new times', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    // Moving to the same time is allowed: the appointment does not block itself.
    $this->booking->reschedule($appointment, bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '11:00'),
        'ends_at' => clinicTime('2026-11-21', '11:30'),
    ]), $this->admin);

    expect($appointment->refresh()->starts_at->format('H:i'))->toBe('11:00');

    $audit = AuditLog::query()->where('action', AuditAction::Update->value)->where('subject_id', $appointment->getKey())->firstOrFail();
    expect(json_encode($audit->changes))->toContain('10:00')->toContain('11:00');

    // Rescheduling writes no status history: it is not a transition.
    expect(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->count())->toBe(1);
});

test('rescheduling into a busy slot is refused and the appointment does not move', function () {
    $first = $this->booking->book(bookingInput($this), $this->admin);
    $this->booking->book(bookingInput($this, [
        'client_id' => Client::factory()->create()->getKey(),
        'starts_at' => clinicTime('2026-11-21', '11:00'),
        'ends_at' => clinicTime('2026-11-21', '11:30'),
    ]), $this->admin);

    expect(fn () => $this->booking->reschedule($first, bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '11:15'),
        'ends_at' => clinicTime('2026-11-21', '11:45'),
    ]), $this->admin))->toThrow(ValidationException::class);

    expect($first->refresh()->starts_at->format('H:i'))->toBe('10:00');
});

test('an arrived appointment cannot be rescheduled', function () {
    $appointment = Appointment::factory()->create(['status' => AppointmentStatus::Arrived]);

    expect(fn () => $this->booking->reschedule($appointment, bookingInput($this), $this->admin))
        ->toThrow(ValidationException::class);
});

test('cancelling needs a reason, and then releases the slot', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    expect(fn () => $this->booking->cancel($appointment, '   ', $this->admin))
        ->toThrow(ValidationException::class);
    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);

    $this->booking->cancel($appointment, 'مراجع درخواست کرد', $this->admin);

    $appointment->refresh();
    expect($appointment->status)->toBe(AppointmentStatus::Cancelled);
    expect($appointment->cancel_reason)->toBe('مراجع درخواست کرد');

    // The slot is free again.
    expect($this->booking->book(bookingInput($this), $this->admin)->exists)->toBeTrue();
});

test('every status change writes a history row with who made it', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    $this->booking->transition($appointment, AppointmentStatus::Confirmed, $this->admin);
    $this->booking->transition($appointment, AppointmentStatus::Arrived, $this->admin);

    $rows = AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->orderBy('id')->get();

    expect($rows->map(fn ($r) => [$r->from_status?->value, $r->to_status->value])->all())->toBe([
        [null, 'scheduled'],
        ['scheduled', 'confirmed'],
        ['confirmed', 'arrived'],
    ]);
    expect($rows->pluck('changed_by')->unique()->all())->toBe([$this->admin->getKey()]);
});

test('a status move is allowed exactly when spec section 7.2 allows it', function (AppointmentStatus $from, AppointmentStatus $to) {
    $appointment = Appointment::factory()->create(['status' => $from]);

    if ($from->canMoveTo($to)) {
        $this->booking->transition($appointment, $to, $this->admin, 'دلیل');
        expect($appointment->refresh()->status)->toBe($to);
    } else {
        expect(fn () => $this->booking->transition($appointment, $to, $this->admin, 'دلیل'))
            ->toThrow(ValidationException::class);
        expect($appointment->refresh()->status)->toBe($from);
    }
})->with(AppointmentStatus::cases())->with(AppointmentStatus::cases());

test('the allowed moves are exactly the ones in spec section 7.2', function () {
    $expected = [
        'scheduled' => ['confirmed', 'arrived', 'cancelled', 'no_show'],
        'confirmed' => ['arrived', 'cancelled', 'no_show'],
        'arrived' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    foreach (AppointmentStatus::cases() as $status) {
        expect(array_map(fn (AppointmentStatus $s) => $s->value, $status->allowedNext()))
            ->toEqualCanonicalizing($expected[$status->value]);
    }
});
