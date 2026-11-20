<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\ServiceKind;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Device;
use App\Models\Measurement;
use App\Models\MeasurementSet;
use App\Models\MeasurementType;
use App\Models\Service;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function measService(): Service
{
    return Service::factory()->create([
        'kind' => ServiceKind::DeviceSession,
        'requires_device' => true,
        'requires_specialist' => false,
        'is_active' => true,
    ]);
}

function measOperator(): User
{
    return User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);
}

function measAt(string $time): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Tehran')->startOfDay()->setTimeFromTimeString($time);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function measAppointment(Client $client, Service $service, AppointmentStatus $status, CarbonImmutable $start, array $overrides = []): Appointment
{
    return Appointment::factory()->create(array_merge([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'device_id' => Device::factory()->create()->getKey(),
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(30),
        'status' => $status,
    ], $overrides));
}

/**
 * The values of a set keyed by type, as stored (decimal strings).
 *
 * @return array<string, string>
 */
function measValues(MeasurementSet $set): array
{
    return $set->measurements()->with('type')->get()
        ->mapWithKeys(fn (Measurement $measurement): array => [(string) $measurement->type?->key => (string) $measurement->value])
        ->all();
}

function measRecord(array $values, ?Client $client = null): MeasurementSet
{
    return app(MeasurementRecorder::class)->record(
        $client ?? makeClient(),
        measOperator(),
        CarbonImmutable::now('Asia/Tehran')->startOfDay(),
        $values,
    );
}

test('the seeder creates the ten measurement types once, and a second run changes nothing', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $this->seed(MeasurementTypeSeeder::class);

    expect(MeasurementType::query()->count())->toBe(10)
        ->and(MeasurementType::query()->where('key', 'weight')->first()?->unit)->toBe('kg')
        ->and(MeasurementType::query()->where('key', 'body_fat_percent')->first()?->unit)->toBe('%')
        ->and(MeasurementType::query()->where('key', 'waist')->first()?->sort_order)->toBe(20);
});

test('a valid set is saved as pending review, with its values to two decimals', function () {
    $this->seed(MeasurementTypeSeeder::class);

    $set = measRecord(['weight' => '72.5', 'waist' => '80', 'blank' => '']);

    expect($set->fresh()->status->value)->toBe('pending_review')
        ->and(measValues($set))->toBe(['weight' => '72.50', 'waist' => '80.00'])
        ->and($set->measured_at->toDateString())->toBe(CarbonImmutable::now('Asia/Tehran')->toDateString());
});

test('the range boundaries are accepted, and outside them the value is rejected, never clamped', function (string $key, string $value, bool $accepted) {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();

    if ($accepted) {
        $set = measRecord([$key => $value], $client);

        expect(measValues($set))->toHaveKey($key);

        return;
    }

    expect(fn () => measRecord([$key => $value], $client))->toThrow(ValidationException::class);
    expect(MeasurementSet::query()->count())->toBe(0)
        ->and(Measurement::query()->count())->toBe(0);
})->with([
    ['weight', '20', true],
    ['weight', '400', true],
    ['weight', '19.99', false],
    ['weight', '400.01', false],
    ['waist', '10', true],
    ['waist', '300', true],
    ['waist', '9.9', false],
    ['waist', '300.5', false],
    ['body_fat_percent', '1', true],
    ['body_fat_percent', '70', true],
    ['body_fat_percent', '0.9', false],
    ['body_fat_percent', '70.1', false],
]);

test('a value with more than two decimals, or a value that is not a number, is rejected', function (string $value) {
    $this->seed(MeasurementTypeSeeder::class);

    expect(fn () => measRecord(['weight' => $value]))->toThrow(ValidationException::class);
})->with(['72.555', 'abc', '7-2', '1,000']);

test('a set with no values is refused', function () {
    $this->seed(MeasurementTypeSeeder::class);

    expect(fn () => measRecord(['weight' => '  ', 'waist' => '']))->toThrow(ValidationException::class);
    expect(MeasurementSet::query()->count())->toBe(0);
});

test('the operator form takes Persian digits and a Persian decimal separator', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $appointment = measAppointment($client, measService(), AppointmentStatus::Arrived, measAt('10:00'));

    $this->actingAs(measOperator())
        ->post(route('operator.measurements.store', $appointment), ['values' => ['weight' => '۷۲٫۵', 'hip' => '۹۵']])
        ->assertRedirect(route('operator.sessions.create', $appointment))
        ->assertSessionHas('success');

    $set = MeasurementSet::query()->firstOrFail();

    expect(measValues($set))->toBe(['weight' => '72.50', 'hip' => '95.00']);
});

test('the operator form ignores the specialist-only types, which the operator cannot send', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $appointment = measAppointment($client, measService(), AppointmentStatus::Arrived, measAt('10:00'));

    $this->actingAs(measOperator())
        ->post(route('operator.measurements.store', $appointment), ['values' => ['weight' => '70', 'body_fat_percent' => '20']])
        ->assertSessionHasNoErrors();

    expect(measValues(MeasurementSet::query()->firstOrFail()))->toBe(['weight' => '70.00']);
});

test('the set is audited with its types and no values', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $appointment = measAppointment($client, measService(), AppointmentStatus::Arrived, measAt('10:00'));

    $this->actingAs(measOperator())
        ->post(route('operator.measurements.store', $appointment), ['values' => ['weight' => '70']]);

    $row = DB::table('audit_logs')->where('action', 'create')->where('subject_type', MeasurementSet::class)->first();

    expect($row)->not->toBeNull()
        ->and(json_decode((string) $row->changes, true)['new']['types'])->toBe(['weight'])
        ->and((string) $row->changes)->not->toContain('70');
});

test('a blank operator form is refused with a message', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $appointment = measAppointment($client, measService(), AppointmentStatus::Arrived, measAt('10:00'));

    $this->actingAs(measOperator())
        ->post(route('operator.measurements.store', $appointment), ['values' => ['weight' => '']])
        ->assertSessionHasErrors(['values']);

    expect(MeasurementSet::query()->count())->toBe(0);
});

test('the record page shows the operator fields and not the specialist-only ones', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $appointment = measAppointment($client, measService(), AppointmentStatus::Arrived, measAt('10:00'));

    $this->actingAs(measOperator())
        ->get(route('operator.sessions.create', $appointment))
        ->assertOk()
        ->assertSee('values[weight]', false)
        ->assertSee('values[hip]', false)
        ->assertDontSee('values[body_fat_percent]', false)
        ->assertDontSee('values[muscle_mass]', false);
});

test('measurements are refused for another day, a cancelled appointment, and a finished one', function () {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $service = measService();
    $operator = measOperator();

    $appointments = [
        measAppointment($client, $service, AppointmentStatus::Arrived, measAt('10:00')->subDay()),
        measAppointment($client, $service, AppointmentStatus::Cancelled, measAt('11:00'), ['cancel_reason' => 'لغو']),
    ];

    foreach ($appointments as $appointment) {
        $this->actingAs($operator)
            ->post(route('operator.measurements.store', $appointment), ['values' => ['weight' => '70']])
            ->assertNotFound();
    }

    $finished = measAppointment($client, $service, AppointmentStatus::Completed, measAt('12:00'));

    $this->actingAs($operator)
        ->post(route('operator.measurements.store', $finished), ['values' => ['weight' => '70']])
        ->assertSessionHasErrors(['status']);

    expect(MeasurementSet::query()->count())->toBe(0);
});

test('only the operator can record measurements; other roles get 403, guests are sent to log in', function (string $role, int $status) {
    $this->seed(MeasurementTypeSeeder::class);
    $client = makeClient();
    $appointment = measAppointment($client, measService(), AppointmentStatus::Arrived, measAt('10:00'));

    $this->actingAs(User::factory()->role(StaffRole::from($role))->create(['is_active' => true]))
        ->post(route('operator.measurements.store', $appointment), ['values' => ['weight' => '70']])
        ->assertStatus($status);
})->with([
    'operator' => ['operator', 302],
    'admin' => ['admin', 403],
    'secretary' => ['secretary', 403],
    'specialist' => ['specialist', 403],
]);
