<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\Service;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Scheduling\BookingService;
use Carbon\CarbonImmutable;

// The reminder runs every 15 minutes and selects appointments starting in [now + 24h, + 15 min) (spec section 9).

beforeEach(function () {
    $this->booking = app(BookingService::class);
    $this->admin = User::factory()->role(StaffRole::Admin)->create();
    $this->service = Service::factory()->create([
        'name' => 'مشاوره تغذیه',
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
    $this->device = Device::factory()->create();
    bookingWorkWeek(ScheduleResource::Device, $this->device->getKey());
    $this->client = Client::factory()->create(['first_name' => 'مریم', 'mobile' => '09121234567']);

    // Now is Friday 09:00. The window for a 24-hour lead is Saturday 09:00 to 09:15.
    CarbonImmutable::setTestNow(clinicTime('2026-11-20', '09:00'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function reminderRuns(): int
{
    return SmsLog::query()->where('template_key', 'appointment_reminder')->count();
}

test('an appointment inside the window gets one reminder, and a second run adds none', function () {
    $appointment = $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '09:10'),
        'ends_at' => clinicTime('2026-11-21', '09:40'),
    ]), $this->admin);

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(reminderRuns())->toBe(1);

    $log = SmsLog::query()->where('template_key', 'appointment_reminder')->firstOrFail();
    expect($log->related_id)->toBe($appointment->getKey())
        ->and($log->body)->toContain('مشاوره تغذیه')
        ->and($log->body)->toContain('۰۹:۱۰');
});

test('appointments outside the window, and cancelled ones, get no reminder', function () {
    $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '09:30'),
        'ends_at' => clinicTime('2026-11-21', '10:00'),
    ]), $this->admin);

    $cancelled = $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '09:05'),
        'ends_at' => clinicTime('2026-11-21', '09:35'),
    ]), $this->admin);
    $this->booking->cancel($cancelled, 'تغییر برنامه', $this->admin);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(reminderRuns())->toBe(0);
});

test('a confirmed appointment gets its reminder too', function () {
    $appointment = $this->booking->book(bookingInput($this, [
        'starts_at' => clinicTime('2026-11-21', '09:05'),
        'ends_at' => clinicTime('2026-11-21', '09:35'),
    ]), $this->admin);

    $this->booking->transition($appointment, AppointmentStatus::Confirmed, $this->admin);
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(reminderRuns())->toBe(1);
});
