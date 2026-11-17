<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Jalali;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(fn () => Carbon::setTestNow());

/** Writes an entry at the given time, through the real logger. */
function auditAt(string $when, AuditAction $action, ?User $actor, ?int $clientId = null): AuditLog
{
    Carbon::setTestNow(Carbon::parse($when, 'Asia/Tehran'));
    $subject = User::factory()->create();

    return app(AuditLogger::class)->log($action, $subject, $clientId, null, $actor);
}

test('guests are sent to the login page', function () {
    $this->get('/admin/audit')->assertRedirect(route('login'));
});

test('only the admin can open the audit log', function (string $role, int $status) {
    $user = User::factory()->role(StaffRole::from($role))->create();

    $this->actingAs($user)->get('/admin/audit')->assertStatus($status);
})->with([
    'admin' => ['admin', 200],
    'secretary' => ['secretary', 403],
    'operator' => ['operator', 403],
    'specialist' => ['specialist', 403],
]);

test('an empty log shows the empty state', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->get('/admin/audit')
        ->assertOk()
        ->assertSee(__('audit.empty'))
        ->assertSee('dir="rtl"', false);
});

test('the action filter shows only that action', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    auditAt('2026-11-17 12:00:00', AuditAction::Login, $admin);
    auditAt('2026-11-17 12:01:00', AuditAction::Create, $admin);

    $this->actingAs($admin)->get('/admin/audit?action=login')
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->action === 'login');
});

test('the actor filter shows only that user', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $other = User::factory()->role(StaffRole::Secretary)->create();
    auditAt('2026-11-17 12:00:00', AuditAction::Login, $admin);
    auditAt('2026-11-17 12:01:00', AuditAction::Login, $other);

    $this->actingAs($admin)->get('/admin/audit?actor='.$other->id)
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->actor_id === $other->id);
});

test('the client filter accepts Persian digits', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    auditAt('2026-11-17 12:00:00', AuditAction::View, $admin, 7);
    auditAt('2026-11-17 12:01:00', AuditAction::View, $admin, 8);

    $this->actingAs($admin)->get('/admin/audit?client=۷')
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->client_id === 7);
});

test('the date range uses Jalali dates and includes both ends of the range', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    auditAt('2026-11-10 12:00:00', AuditAction::Login, $admin);
    auditAt('2026-11-17 12:00:00', AuditAction::Login, $admin);

    [$jy, $jm, $jd] = Jalali::fromGregorian(2026, 11, 17);
    $day = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

    $this->actingAs($admin)->get('/admin/audit?from='.$day.'&to='.$day)
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);

    $this->actingAs($admin)->get('/admin/audit?to='.$day)
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 2);
});

test('an invalid Jalali date is refused with a message', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($admin)->get('/admin/audit?from=1405/13/40')
        ->assertSessionHasErrors('from');
});

test('the log is paginated at 50 entries per page', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    for ($i = 0; $i < 55; $i++) {
        auditAt('2026-11-17 12:00:00', AuditAction::View, $admin, 1);
    }

    $this->actingAs($admin)->get('/admin/audit')
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 55 && $logs->count() === 50);

    $this->actingAs($admin)->get('/admin/audit?page=2')
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 5);
});

test('the page resolves actor names in one query, not one per row', function () {
    $admin = User::factory()->role(StaffRole::Admin)->create();
    $secretary = User::factory()->role(StaffRole::Secretary)->create(['name' => 'منشی تست']);
    for ($i = 0; $i < 5; $i++) {
        auditAt('2026-11-17 12:00:00', AuditAction::Login, $secretary);
    }

    $this->actingAs($admin);
    DB::enableQueryLog();
    $this->get('/admin/audit')->assertOk()->assertSee('منشی تست');

    $userQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'from "users"') || str_contains($q['query'], 'from `users`'))
        ->count();

    // Session, auth and the staff dropdown read users too. The actor names add only one more query.
    expect($userQueries)->toBeLessThanOrEqual(4);
});
