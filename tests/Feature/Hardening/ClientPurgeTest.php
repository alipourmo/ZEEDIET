<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\PaymentMethod;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Payments\PaymentService;
use App\Services\Reports\ReportWorkflow;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;
use Illuminate\Support\Facades\DB;

// clients:purge (spec section 10.10): only a deleted client, only after the retention period, only with --confirm.

beforeEach(function () {
    config(['clinic.retention_days' => 30]);
});

test('a client who is not deleted cannot be purged', function () {
    $client = makeClient();

    $this->artisan('clients:purge', ['client' => $client->getKey(), '--confirm' => true])->assertFailed();

    expect(Client::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
});

test('a client cannot be purged before the retention period has passed', function () {
    $client = makeClient();
    $client->delete();

    $this->artisan('clients:purge', ['client' => $client->getKey(), '--confirm' => true])->assertFailed();

    expect(Client::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
});

test('without a retention period, nothing can be purged', function () {
    config(['clinic.retention_days' => null]);

    $client = makeClient();
    $client->delete();
    $this->travel(400)->days();

    $this->artisan('clients:purge', ['client' => $client->getKey(), '--confirm' => true])->assertFailed();

    expect(Client::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
});

test('without --confirm the purge only shows the plan', function () {
    $client = makeClient();
    $client->delete();
    $this->travel(31)->days();

    $this->artisan('clients:purge', ['client' => $client->getKey()])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Client::withTrashed()->whereKey($client->getKey())->exists())->toBeTrue();
});

test('the purge removes the client and every row that belongs to them, keeps other clients, and is audited', function () {
    $this->seed(MeasurementTypeSeeder::class);

    $client = makeClient(['mobile' => '09121234567']);
    $other = makeClient(['mobile' => '09129998888']);
    $secretary = User::factory()->role(StaffRole::Secretary)->create(['is_active' => true]);
    $admin = User::factory()->role(StaffRole::Admin)->create(['is_active' => true]);
    $specialist = User::factory()->role(StaffRole::Specialist)->create(['is_active' => true]);
    $operator = User::factory()->role(StaffRole::Operator)->create(['is_active' => true]);

    $service = Service::factory()->create(['requires_device' => false, 'requires_specialist' => true, 'is_active' => true]);

    $appointment = Appointment::query()->create([
        'client_id' => $client->getKey(),
        'service_id' => $service->getKey(),
        'starts_at' => CarbonImmutable::now('Asia/Tehran')->addDay(),
        'ends_at' => CarbonImmutable::now('Asia/Tehran')->addDay()->addHour(),
        'status' => AppointmentStatus::Scheduled,
        'source' => 'staff',
    ]);

    $payment = app(PaymentService::class)->record([
        'client_id' => $client->getKey(),
        'appointment_id' => $appointment->getKey(),
        'package_id' => null,
        'amount' => 120000,
        'method' => PaymentMethod::Cash,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $secretary);

    // A reversal refers to the payment, and a correction refers to the published set: both self references.
    app(PaymentService::class)->reverse($payment, 'اشتباه ثبت شد', $admin);

    $measured = CarbonImmutable::now('Asia/Tehran')->startOfDay();
    $first = app(MeasurementRecorder::class)->record($client, $operator, $measured, ['weight' => '80.0']);
    $workflow = app(ReportWorkflow::class);
    $published = $workflow->publish($first, $specialist);
    $correction = $workflow->correct($published, $specialist, ['weight' => '78.0']);
    $workflow->publish($correction, $specialist);

    app(PaymentService::class)->record([
        'client_id' => $other->getKey(),
        'appointment_id' => null,
        'package_id' => null,
        'amount' => 50000,
        'method' => PaymentMethod::Card,
        'paid_at' => CarbonImmutable::now('Asia/Tehran'),
        'reference' => null,
        'note' => null,
    ], $secretary);

    $client->delete();
    $this->travel(31)->days();

    $this->artisan('clients:purge', ['client' => $client->getKey(), '--confirm' => true])
        ->expectsConfirmation('Permanently delete client '.$client->getKey().' and the rows above? This cannot be undone.', 'yes')
        ->assertSuccessful();

    foreach (['appointments', 'payments', 'measurement_sets', 'specialist_notes', 'client_medical', 'session_packages'] as $table) {
        $this->assertSame(0, DB::table($table)->where('client_id', $client->getKey())->count(), "rows left in {$table}");
    }

    expect(DB::table('clients')->where('id', $client->getKey())->exists())->toBeFalse();

    expect(Client::withTrashed()->whereKey($other->getKey())->exists())->toBeTrue()
        ->and(DB::table('payments')->where('client_id', $other->getKey())->count())->toBe(1);

    $audit = AuditLog::query()
        ->where('action', AuditAction::Delete->value)
        ->where('subject_type', Client::class)
        ->where('subject_id', $client->getKey())
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit?->actor_type)->toBe('system')
        ->and(json_encode($audit?->changes))->not->toContain('09121234567');
});
