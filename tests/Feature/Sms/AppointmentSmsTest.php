<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleResource;
use App\Enums\SmsStatus;
use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\Service;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Scheduling\BookingService;
use Illuminate\Validation\ValidationException;

// Booking, rescheduling and cancelling send the SMS of spec section 9. The helpers come from BookingServiceTest.

beforeEach(function () {
    $this->booking = app(BookingService::class);
    $this->admin = User::factory()->role(StaffRole::Admin)->create();
    $this->service = Service::factory()->create([
        'name' => 'آنالیز بدن',
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
    $this->device = Device::factory()->create();
    bookingWorkWeek(ScheduleResource::Device, $this->device->getKey());
    $this->client = Client::factory()->create(['first_name' => 'سارا', 'mobile' => '09121234567']);
});

test('booking queues an appointment_confirmed SMS to the client, after the booking is saved', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    $log = SmsLog::query()->where('template_key', 'appointment_confirmed')->firstOrFail();

    expect($log->mobile)->toBe('09121234567')
        ->and($log->status)->toBe(SmsStatus::Sent)
        ->and($log->related_type)->toBe($appointment::class)
        ->and($log->related_id)->toBe($appointment->getKey())
        ->and($log->body)->toContain('سارا')
        ->and($log->body)->toContain('آنالیز بدن')
        ->and($log->body)->toContain('۱۰:۰۰');
});

test('a refused booking sends nothing', function () {
    $this->booking->book(bookingInput($this), $this->admin);
    $count = SmsLog::query()->count();

    expect(fn () => $this->booking->book(bookingInput($this), $this->admin))->toThrow(ValidationException::class);

    expect(SmsLog::query()->count())->toBe($count);
});

test('rescheduling queues appointment_rescheduled with the new time', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    $this->booking->reschedule($appointment, bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '11:00'),
        'ends_at' => clinicTime('2026-11-21', '11:30'),
    ]), $this->admin);

    $log = SmsLog::query()->where('template_key', 'appointment_rescheduled')->firstOrFail();

    expect($log->body)->toContain('۱۱:۰۰');
});

test('cancelling queues appointment_cancelled, and a plain status change queues nothing', function () {
    $appointment = $this->booking->book(bookingInput($this), $this->admin);

    $this->booking->transition($appointment, AppointmentStatus::Confirmed, $this->admin);
    expect(SmsLog::query()->where('template_key', 'appointment_cancelled')->count())->toBe(0);

    $this->booking->cancel($appointment, 'مراجع درخواست کرد', $this->admin);

    expect(SmsLog::query()->where('template_key', 'appointment_cancelled')->count())->toBe(1);
});
