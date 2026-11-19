<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\StaffRole;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\User;

/**
 * An appointment in the given status. The status is set directly, because these tests are about the moves.
 *
 * @param  array<string, mixed>  $overrides
 */
function transitionFixture(AppointmentStatus $status, array $overrides = []): Appointment
{
    return Appointment::factory()->create(array_merge(['status' => $status], $overrides));
}

function transitionUrl(Appointment $appointment): string
{
    return route('admin.appointments.transition', $appointment);
}

function transitionStaff(StaffRole $role = StaffRole::Secretary): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

test('only admin and secretary can change an appointment status; the others are refused', function (string $role, int $status) {
    $appointment = transitionFixture(AppointmentStatus::Scheduled);

    $this->actingAs(transitionStaff(StaffRole::from($role)))
        ->post(transitionUrl($appointment), ['to' => 'confirmed'])
        ->assertStatus($status);

    $expected = $status === 302 ? AppointmentStatus::Confirmed : AppointmentStatus::Scheduled;
    expect($appointment->fresh()->currentStatus())->toBe($expected);
})->with([
    'admin' => ['admin', 302],
    'secretary' => ['secretary', 302],
    'operator' => ['operator', 403],
    'specialist' => ['specialist', 403],
]);

test('a guest is sent to log in and nothing changes', function () {
    $appointment = transitionFixture(AppointmentStatus::Scheduled);

    $this->post(transitionUrl($appointment), ['to' => 'confirmed'])->assertRedirect();

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Scheduled);
});

test('each offered move writes one status history row and one audit row', function (AppointmentStatus $from, string $to) {
    $appointment = transitionFixture($from);

    $this->actingAs(transitionStaff())
        ->post(transitionUrl($appointment), ['to' => $to, 'cancel_reason' => $to === 'cancelled' ? 'بیمار تماس گرفت' : null])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::from($to));

    $history = AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->get();
    expect($history)->toHaveCount(1)
        ->and($history->first()->from_status)->toBe($from)
        ->and($history->first()->to_status)->toBe(AppointmentStatus::from($to));

    expect(AuditLog::query()->where('action', AuditAction::Update->value)->where('subject_id', $appointment->getKey())->count())
        ->toBe(1);
})->with([
    'scheduled to confirmed' => [AppointmentStatus::Scheduled, 'confirmed'],
    'scheduled to arrived' => [AppointmentStatus::Scheduled, 'arrived'],
    'scheduled to no-show' => [AppointmentStatus::Scheduled, 'no_show'],
    'scheduled to cancelled' => [AppointmentStatus::Scheduled, 'cancelled'],
    'confirmed to arrived' => [AppointmentStatus::Confirmed, 'arrived'],
    'confirmed to no-show' => [AppointmentStatus::Confirmed, 'no_show'],
    'confirmed to cancelled' => [AppointmentStatus::Confirmed, 'cancelled'],
    'arrived to cancelled' => [AppointmentStatus::Arrived, 'cancelled'],
]);

test('a cancel stores the trimmed reason on the appointment and in the audit row', function () {
    $appointment = transitionFixture(AppointmentStatus::Scheduled);

    $this->actingAs(transitionStaff())
        ->post(transitionUrl($appointment), ['to' => 'cancelled', 'cancel_reason' => '  بیمار مسافرت است  '])
        ->assertRedirect();

    $fresh = $appointment->fresh();
    expect($fresh->currentStatus())->toBe(AppointmentStatus::Cancelled)
        ->and($fresh->cancel_reason)->toBe('بیمار مسافرت است');

    $audit = AuditLog::query()
        ->where('action', AuditAction::Update->value)
        ->where('subject_id', $appointment->getKey())
        ->firstOrFail();

    expect($audit->changes['new']['cancel_reason'])->toBe('بیمار مسافرت است');
});

test('a cancel without a reason is refused and changes nothing', function (string $reason) {
    $appointment = transitionFixture(AppointmentStatus::Scheduled);

    $this->actingAs(transitionStaff())
        ->post(transitionUrl($appointment), ['to' => 'cancelled', 'cancel_reason' => $reason])
        ->assertSessionHasErrors('cancel_reason');

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Scheduled)
        ->and(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->count())->toBe(0);
})->with([
    'empty' => [''],
    'spaces only' => ['   '],
]);

test('a move the state machine does not allow is refused and changes nothing', function (AppointmentStatus $from, string $to, string $field) {
    $appointment = transitionFixture($from);

    $this->actingAs(transitionStaff())
        ->post(transitionUrl($appointment), ['to' => $to, 'cancel_reason' => 'بررسی'])
        ->assertSessionHasErrors($field);

    expect($appointment->fresh()->currentStatus())->toBe($from)
        ->and(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->count())->toBe(0);
})->with([
    'arrived to no-show' => [AppointmentStatus::Arrived, 'no_show', 'status'],
    'confirmed to confirmed' => [AppointmentStatus::Confirmed, 'confirmed', 'status'],
    'no-show to arrived' => [AppointmentStatus::NoShow, 'arrived', 'status'],
    'cancelled to confirmed' => [AppointmentStatus::Cancelled, 'confirmed', 'status'],
    'completed to cancelled' => [AppointmentStatus::Completed, 'cancelled', 'status'],
    'scheduled to completed (not offered on the staff screen)' => [AppointmentStatus::Scheduled, 'completed', 'to'],
    'arrived to in progress (not offered until S3)' => [AppointmentStatus::Arrived, 'in_progress', 'to'],
    'a status that does not exist' => [AppointmentStatus::Scheduled, 'deleted', 'to'],
]);

test('a stale page cannot move an appointment again', function () {
    $appointment = transitionFixture(AppointmentStatus::Scheduled);
    $secretary = transitionStaff();

    $this->actingAs($secretary)->post(transitionUrl($appointment), ['to' => 'arrived'])->assertRedirect();

    // The second request comes from a page that still showed "scheduled". Arrived does not move to no-show.
    $this->actingAs($secretary)->post(transitionUrl($appointment), ['to' => 'no_show'])->assertSessionHasErrors('status');

    expect($appointment->fresh()->currentStatus())->toBe(AppointmentStatus::Arrived)
        ->and(AppointmentStatusHistory::query()->where('appointment_id', $appointment->getKey())->count())->toBe(1);
});

test('the appointment page offers only the moves its status allows', function (AppointmentStatus $status) {
    // The Persian labels of each move, as lang/fa/appointments.php defines them.
    $labels = [
        'confirmed' => 'تأیید نوبت',
        'arrived' => 'ثبت حضور',
        'no_show' => 'ثبت عدم حضور',
        'cancelled' => 'لغو نوبت',
    ];
    $offered = match ($status) {
        AppointmentStatus::Scheduled => ['confirmed', 'arrived', 'no_show', 'cancelled'],
        AppointmentStatus::Confirmed => ['arrived', 'no_show', 'cancelled'],
        AppointmentStatus::Arrived => ['cancelled'],
        default => [],
    };

    $appointment = transitionFixture($status);
    $response = $this->actingAs(transitionStaff())->get(route('admin.appointments.edit', $appointment));

    $response->assertOk();

    if ($offered === []) {
        $response->assertSee(__('appointments.no_actions'));
    }

    foreach ($labels as $to => $label) {
        if (in_array($to, $offered, true)) {
            $response->assertSee($label);
        } else {
            $response->assertDontSee($label);
        }
    }
})->with([
    [AppointmentStatus::Scheduled],
    [AppointmentStatus::Confirmed],
    [AppointmentStatus::Arrived],
    [AppointmentStatus::Cancelled],
]);
