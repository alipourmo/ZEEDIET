<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\ScheduleResource;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Device;
use App\Models\MeasurementSet;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Payments\PaymentService;
use App\Services\Reports\ReportWorkflow;
use App\Services\Scheduling\BookingService;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;

// Cross-client access returns 404; unpublished sets are never shown (spec 5, 8.5 and 12 S4 acceptance).

beforeEach(function () {
    $this->seed(MeasurementTypeSeeder::class);
    $this->me = makeClient(['mobile' => '09121234567', 'first_name' => 'سارا']);
    $this->other = makeClient(['mobile' => '09129998888', 'first_name' => 'نازنین']);
});

/** Signs the client in directly (the OTP flow has its own tests), with the session keys the portal middleware reads. */
function portalActAs($test, Client $client): void
{
    $test->actingAs($client, 'client')->withSession([
        'portal.login_at' => now()->timestamp,
        'portal.seen_at' => now()->timestamp,
    ]);
}

/** A specialist-published report for the client. */
function portalPublished(Client $client, array $values = ['weight' => '72.5', 'waist' => '88']): MeasurementSet
{
    $specialist = User::factory()->role(StaffRole::Specialist)->create(['is_active' => true]);
    $operator = User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);

    $pending = app(MeasurementRecorder::class)->record(
        $client,
        $operator,
        CarbonImmutable::now('Asia/Tehran')->startOfDay(),
        $values,
    );

    return app(ReportWorkflow::class)->publish($pending, $specialist);
}

/** A set that is still waiting for the specialist. */
function portalPending(Client $client): MeasurementSet
{
    $operator = User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);

    return app(MeasurementRecorder::class)->record(
        $client,
        $operator,
        CarbonImmutable::now('Asia/Tehran')->startOfDay(),
        ['weight' => '99.9'],
    );
}

test('a guest is sent to the portal sign-in page, not the staff one', function () {
    $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
    $this->get(route('portal.reports'))->assertRedirect(route('portal.login'));
});

test('a client sees only their own published reports', function () {
    $mine = portalPublished($this->me);
    $theirs = portalPublished($this->other, ['weight' => '91.0']);
    portalPending($this->me);

    portalActAs($this, $this->me);

    $this->get(route('portal.reports'))
        ->assertOk()
        ->assertSee(route('portal.reports.show', $mine), false)
        ->assertDontSee(route('portal.reports.show', $theirs), false);
});

test('an open report shows its values and the change from the first published one', function () {
    portalPublished($this->me, ['weight' => '80.0']);
    $latest = portalPublished($this->me, ['weight' => '75.5']);

    portalActAs($this, $this->me);

    $this->get(route('portal.reports.show', $latest))
        ->assertOk()
        ->assertSee(\App\Support\Digits::toPersian('-4.50'))
        ->assertSee(\App\Support\Digits::toPersian('75'));
});

test('another client\'s report is not found: 404', function () {
    $theirs = portalPublished($this->other);

    portalActAs($this, $this->me);

    $this->get(route('portal.reports.show', $theirs))->assertNotFound();
});

test('an unpublished set is not found, even for its own client', function () {
    $pending = portalPending($this->me);

    portalActAs($this, $this->me);

    $this->get(route('portal.reports.show', $pending))->assertNotFound();
    $this->get(route('portal.reports'))->assertDontSee(route('portal.reports.show', $pending), false);
    $this->get(route('portal.progress'))->assertOk()->assertDontSee(\App\Support\Digits::toPersian('99.9'));
});

test('a superseded published set is not found: only the latest version is shown', function () {
    $old = portalPublished($this->me, ['weight' => '80.0']);
    portalPublished($this->me, ['weight' => '78.0']);

    portalActAs($this, $this->me);

    $this->get(route('portal.reports.show', $old))->assertNotFound();
});

test('appointments list shows only the client\'s own, and they are read-only', function () {
    $booking = app(BookingService::class);
    $admin = User::factory()->role(StaffRole::Admin)->create(['is_active' => true]);
    $device = Device::factory()->create();
    bookingWorkWeek(ScheduleResource::Device, $device->getKey());

    $mineService = Service::factory()->create(['name' => 'جلسه مخصوص من', 'requires_device' => true, 'requires_specialist' => false, 'is_active' => true]);
    $theirsService = Service::factory()->create(['name' => 'جلسه دیگری', 'requires_device' => true, 'requires_specialist' => false, 'is_active' => true]);

    $booking->book(bookingInput((object) ['client' => $this->me, 'service' => $mineService, 'device' => $device], [
        'starts_at' => clinicTime('2026-11-21', '10:00'),
        'ends_at' => clinicTime('2026-11-21', '10:30'),
    ]), $admin);
    $booking->book(bookingInput((object) ['client' => $this->other, 'service' => $theirsService, 'device' => $device], [
        'starts_at' => clinicTime('2026-11-21', '11:00'),
        'ends_at' => clinicTime('2026-11-21', '11:30'),
    ]), $admin);

    portalActAs($this, $this->me);

    $this->get(route('portal.appointments'))
        ->assertOk()
        ->assertSee('جلسه مخصوص من')
        ->assertDontSee('جلسه دیگری')
        ->assertDontSee('<form', false);
});

test('a cancelled appointment is not counted as upcoming on the home page', function () {
    Appointment::query()->create([
        'client_id' => $this->me->getKey(),
        'service_id' => Service::factory()->create()->getKey(),
        'starts_at' => CarbonImmutable::now('Asia/Tehran')->addDay(),
        'ends_at' => CarbonImmutable::now('Asia/Tehran')->addDay()->addHour(),
        'status' => AppointmentStatus::Cancelled,
        'source' => 'staff',
    ]);

    portalActAs($this, $this->me);

    $this->get(route('portal.home'))->assertOk()->assertSee('۰ نوبت پیش رو');
});

test('remaining sessions show only the client\'s own packages', function () {
    $service = Service::factory()->create(['name' => 'بسته من']);
    SessionPackage::factory()->create(['client_id' => $this->me->getKey(), 'service_id' => $service->getKey(), 'total_sessions' => 5]);
    SessionPackage::factory()->create([
        'client_id' => $this->other->getKey(),
        'service_id' => Service::factory()->create(['name' => 'بسته دیگری'])->getKey(),
        'total_sessions' => 9,
    ]);

    portalActAs($this, $this->me);

    $this->get(route('portal.sessions'))
        ->assertOk()
        ->assertSee('بسته من')
        ->assertDontSee('بسته دیگری');
});

test('payments list shows only the client\'s own, with the net total', function () {
    $by = User::factory()->role(StaffRole::Secretary)->create(['is_active' => true]);
    $payments = app(PaymentService::class);

    $payments->record([
        'client_id' => $this->me->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => 120000,
        'method' => PaymentMethod::Cash,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $by);
    $payments->record([
        'client_id' => $this->other->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => 987000,
        'method' => PaymentMethod::Card,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $by);

    portalActAs($this, $this->me);

    $this->get(route('portal.payments'))
        ->assertOk()
        ->assertSee(\App\Support\Display::money(120000))
        ->assertDontSee(\App\Support\Display::money(987000));
});

test('a client cannot reach the staff panels: they are treated as guests there', function () {
    portalActAs($this, $this->me);

    // Staff routes check the default guard under 'auth', so a client is sent to staff sign-in, not into the panel.
    $this->get(route('admin.home'))->assertRedirect(route('login'));
    $this->get(route('specialist.reviews.index'))->assertRedirect(route('login'));
});

test('a staff user cannot read the portal', function () {
    $this->actingAs(User::factory()->role(StaffRole::Admin)->create(['is_active' => true]))
        ->get(route('portal.reports'))
        ->assertRedirect(route('portal.login'));
});
