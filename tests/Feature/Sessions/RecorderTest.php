<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\DeviceSessionStatus;
use App\Enums\ServiceKind;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Sessions\SessionRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function recService(): Service
{
    return Service::factory()->create([
        'kind' => ServiceKind::DeviceSession,
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
}

function recOperator(): User
{
    return User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);
}

/** A clinic time today (Asia/Tehran). */
function recAt(string $time): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Tehran')->startOfDay()->setTimeFromTimeString($time);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function recAppointment(Client $client, Service $service, Device $device, AppointmentStatus $status, CarbonImmutable $start, array $overrides = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'device_id' => $device->getKey(),
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(30),
        'status' => $status,
    ], $overrides));
}

/**
 * Sets the client's medical flags (makeClient() creates an empty record).
 *
 * @param  array<string, mixed>  $attributes
 */
function recMedical(Client $client, array $attributes): void
{
    $client->load('medical');
    $client->medical?->update($attributes);
}

/**
 * 'same' when the two arrays match, ignoring key order (MySQL JSON columns do not keep it). Otherwise the text says
 * what differed, so a failing CI annotation shows the values.
 *
 * @param  array<array-key, mixed>  $actual
 * @param  array<array-key, mixed>  $expected
 */
function recDiff(array $actual, array $expected): string
{
    ksort($actual);
    ksort($expected);

    $actualJson = (string) json_encode($actual, JSON_UNESCAPED_UNICODE);
    $expectedJson = (string) json_encode($expected, JSON_UNESCAPED_UNICODE);

    return $actualJson === $expectedJson ? 'same' : 'differs: '.$actualJson.' expected '.$expectedJson;
}

// Service level ----------------------------------------------------------------------------------------------------

test('sessions are numbered per client and device', function () {
    $client = makeClient();
    $service = recService();
    $device = Device::factory()->create();
    $otherDevice = Device::factory()->create();
    $operator = recOperator();
    $recorder = app(SessionRecorder::class);

    $first = recAppointment($client, $service, $device, AppointmentStatus::Arrived, recAt('09:00'));
    $second = recAppointment($client, $service, $device, AppointmentStatus::Arrived, recAt('10:00'));
    $elsewhere = recAppointment($client, $service, $otherDevice, AppointmentStatus::Arrived, recAt('11:00'));

    expect($recorder->record($first, $operator, [])->session_number)->toBe(1)
        ->and($recorder->record($second, $operator, [])->session_number)->toBe(2)
        ->and($recorder->record($elsewhere, $operator, [])->session_number)->toBe(1);
});

test('the first session moves an arrived appointment to in progress, and the history records it', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    app(SessionRecorder::class)->record($appointment, recOperator(), []);

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::InProgress)
        ->and(AppointmentStatusHistory::query()
            ->where('appointment_id', $appointment->getKey())
            ->where('to_status', AppointmentStatus::InProgress->value)
            ->exists())->toBeTrue();
});

test('a session on a contraindicated device is refused, nothing is saved, and the refusal is audited', function () {
    $client = makeClient();
    recMedical($client, ['device_contraindications' => ['pregnancy', 'thrombosis']]);
    $device = Device::factory()->create(['contraindications' => ['pregnancy']]);
    $appointment = recAppointment($client, recService(), $device, AppointmentStatus::Arrived, recAt('10:00'));

    expect(fn () => app(SessionRecorder::class)->record($appointment, recOperator(), []))
        ->toThrow(ValidationException::class);

    expect(DeviceSession::query()->count())->toBe(0)
        ->and($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Arrived);

    $row = DB::table('audit_logs')->where('action', 'session_refused')->first();

    expect($row)->not->toBeNull()
        ->and($row->subject_type)->toBe(Appointment::class)
        ->and((int) $row->client_id)->toBe($client->getKey())
        ->and(recDiff((array) json_decode((string) $row->changes, true), ['device_id' => (int) $device->getKey(), 'keys' => ['pregnancy']]))
            ->toBe('same');
});

test('an appointment that is not open for a session is refused', function (string $case) {
    $client = makeClient();
    $device = Device::factory()->create();
    $service = recService();

    $appointment = match ($case) {
        'yesterday' => recAppointment($client, $service, $device, AppointmentStatus::Arrived, recAt('10:00')->subDay()),
        'scheduled' => recAppointment($client, $service, $device, AppointmentStatus::Scheduled, recAt('10:00')),
        'a service that needs no device' => recAppointment(
            $client,
            Service::factory()->create(['requires_device' => false, 'requires_specialist' => false, 'is_active' => true]),
            $device,
            AppointmentStatus::Arrived,
            recAt('10:00'),
        ),
        'no device' => recAppointment($client, $service, $device, AppointmentStatus::Arrived, recAt('10:00'), ['device_id' => null]),
    };

    expect(fn () => app(SessionRecorder::class)->record($appointment, recOperator(), []))
        ->toThrow(ValidationException::class);
    expect(DeviceSession::query()->count())->toBe(0);
})->with(['yesterday', 'scheduled', 'a service that needs no device', 'no device']);

test('the session links to the oldest package that still has remaining sessions', function () {
    $client = makeClient();
    $service = recService();
    $device = Device::factory()->create();
    $operator = recOperator();

    $used = SessionPackage::factory()->create([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'total_sessions' => 1,
        'purchased_at' => '2026-01-01',
    ]);
    DeviceSession::factory()->create([
        'client_id' => $client->getKey(),
        'device_id' => $device->getKey(),
        'package_id' => $used->getKey(),
        'operator_id' => $operator->getKey(),
        'status' => DeviceSessionStatus::Done,
    ]);
    $newer = SessionPackage::factory()->create([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'total_sessions' => 2,
        'purchased_at' => '2026-02-01',
    ]);

    $recorder = app(SessionRecorder::class);
    $packages = [];

    foreach (['09:00', '10:00', '11:00'] as $time) {
        $appointment = recAppointment($client, $service, $device, AppointmentStatus::Arrived, recAt($time));
        $packages[] = $recorder->record($appointment, $operator, [])->package_id;
    }

    expect($packages)->toBe([$newer->getKey(), $newer->getKey(), null]);
});

test('a session with no package left, or only a package for another service, has no package', function () {
    $client = makeClient();
    $device = Device::factory()->create();
    $consultation = Service::factory()->create(['requires_specialist' => true, 'is_active' => true]);

    SessionPackage::factory()->create([
        'client_id' => $client->getKey(),
        'service_id' => $consultation->getKey(),
        'total_sessions' => 5,
        'purchased_at' => '2026-01-01',
    ]);

    $appointment = recAppointment($client, recService(), $device, AppointmentStatus::Arrived, recAt('10:00'));
    $session = app(SessionRecorder::class)->record($appointment, recOperator(), ['note' => 'بدون بسته']);

    expect($session->package_id)->toBeNull()
        ->and($session->note)->toBe('بدون بسته');
});

test('a session writes one create audit row with the session fields and no medical text', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    app(SessionRecorder::class)->record($appointment, recOperator(), ['note' => 'متن یادداشت', 'area' => 'شکم']);

    $row = DB::table('audit_logs')->where('action', 'create')->where('subject_type', DeviceSession::class)->first();

    expect($row)->not->toBeNull();

    $new = json_decode((string) $row->changes, true)['new'];

    expect(array_keys($new))->toEqualCanonicalizing(['session_number', 'device_id', 'package_id', 'status'])
        ->and((string) $row->changes)->not->toContain('متن یادداشت')
        ->and((string) $row->changes)->not->toContain('شکم');
});

test('finishing an arrived appointment moves it through in progress to completed', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    app(SessionRecorder::class)->finish($appointment, recOperator());

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Completed)
        ->and(AppointmentStatusHistory::query()
            ->where('appointment_id', $appointment->getKey())
            ->orderBy('id')
            ->pluck('to_status')
            ->map(fn (AppointmentStatus $status): string => $status->value)
            ->all())->toBe(['in_progress', 'completed']);
});

test('finish refuses an appointment that is neither arrived nor in progress', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Scheduled, recAt('10:00'));

    expect(fn () => app(SessionRecorder::class)->finish($appointment, recOperator()))
        ->toThrow(ValidationException::class);
    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Scheduled);
});

// HTTP -------------------------------------------------------------------------------------------------------------

test('the record page shows badges and remaining sessions, and no medical free text', function () {
    $client = makeClient(['first_name' => 'نگار', 'last_name' => 'احمدی']);
    recMedical($client, ['food_allergies' => 'گردو', 'device_contraindications' => []]);
    $service = recService();
    $appointment = recAppointment($client, $service, Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    SessionPackage::factory()->create([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'total_sessions' => 3,
        'purchased_at' => '2026-03-01',
    ]);

    $this->actingAs(recOperator())
        ->get(route('operator.sessions.create', $appointment))
        ->assertOk()
        ->assertSee('نگار')
        ->assertSee(__('alerts.allergy_yes'))
        ->assertSee(__('sessions.remaining_of', ['remaining' => '۳', 'total' => '۳']))
        ->assertDontSee('گردو');
});

test('the operator records a session over HTTP and the parameters are stored', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    $this->actingAs(recOperator())
        ->post(route('operator.sessions.store', $appointment), [
            'intensity' => '۳',
            'area' => 'شکم',
            'duration_min' => '۴۵',
            'note' => '',
        ])
        ->assertRedirect(route('operator.sessions.create', $appointment))
        ->assertSessionHas('success');

    $session = DeviceSession::query()->firstOrFail();

    expect(recDiff((array) $session->parameters, ['intensity' => '3', 'area' => 'شکم', 'duration_min' => 45]))->toBe('same');
    expect($session->parameters)->toBeArray()
        ->and($session->note)->toBeNull()
        ->and($session->session_number)->toBe(1);
});

test('a contraindicated session is refused over HTTP with the specialist message, and nothing is saved', function () {
    $client = makeClient();
    recMedical($client, ['device_contraindications' => ['pregnancy']]);
    $device = Device::factory()->create(['contraindications' => ['pregnancy']]);
    $appointment = recAppointment($client, recService(), $device, AppointmentStatus::Arrived, recAt('10:00'));

    $this->actingAs(recOperator())
        ->post(route('operator.sessions.store', $appointment), [])
        ->assertSessionHasErrors(['contraindication' => __('sessions.refused_contraindication')]);

    expect(DeviceSession::query()->count())->toBe(0);
});

test('another day, a non-device appointment and a cancelled appointment give 404 on every action', function () {
    $client = makeClient();
    $device = Device::factory()->create();
    $operator = recOperator();

    $appointments = [
        recAppointment($client, recService(), $device, AppointmentStatus::Arrived, recAt('10:00')->subDay()),
        recAppointment(
            $client,
            Service::factory()->create(['requires_device' => false, 'requires_specialist' => false, 'is_active' => true]),
            $device,
            AppointmentStatus::Arrived,
            recAt('11:00'),
        ),
        recAppointment($client, recService(), $device, AppointmentStatus::Cancelled, recAt('12:00'), ['cancel_reason' => 'لغو']),
    ];

    foreach ($appointments as $appointment) {
        $this->actingAs($operator)->get(route('operator.sessions.create', $appointment))->assertNotFound();
        $this->actingAs($operator)->post(route('operator.sessions.store', $appointment), [])->assertNotFound();
        $this->actingAs($operator)->post(route('operator.sessions.finish', $appointment))->assertNotFound();
    }

    expect(DeviceSession::query()->count())->toBe(0)
        ->and(collect($appointments)->map(fn (Appointment $a) => $a->fresh()->currentStatus()->value)->all())
        ->toBe(['arrived', 'arrived', 'cancelled']);
});

test('the operator finishes the appointment and returns to the list', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    $this->actingAs(recOperator())
        ->post(route('operator.sessions.finish', $appointment))
        ->assertRedirect(route('operator.today'));

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Completed);
});

test('only the operator can open the record page; other roles get 403, guests are sent to log in', function (string $role, int $status) {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    $this->actingAs(User::factory()->role(StaffRole::from($role))->create(['is_active' => true]))
        ->get(route('operator.sessions.create', $appointment))
        ->assertStatus($status);
})->with([
    'operator' => ['operator', 200],
    'admin' => ['admin', 403],
    'secretary' => ['secretary', 403],
    'specialist' => ['specialist', 403],
]);

test('a guest is sent to log in from the record page', function () {
    $client = makeClient();
    $appointment = recAppointment($client, recService(), Device::factory()->create(), AppointmentStatus::Arrived, recAt('10:00'));

    $this->get(route('operator.sessions.create', $appointment))->assertRedirect();
});
