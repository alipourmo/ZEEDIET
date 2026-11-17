<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;

test('a successful login writes a login entry with the user as actor and subject, with IP and user agent', function () {
    $user = User::factory()->role(StaffRole::Operator)->create(['mobile' => '09121234567']);

    $this->withHeaders(['User-Agent' => 'AuditTest/1.0'])
        ->post('/login', ['mobile' => '09121234567', 'password' => 'password']);

    $log = AuditLog::query()->where('action', 'login')->sole();

    expect($log->actor_type)->toBe('user')
        ->and($log->actor_id)->toBe($user->id)
        ->and($log->subject_type)->toBe(User::class)
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->user_agent)->toBe('AuditTest/1.0');
});

test('a failed login writes no login entry', function () {
    User::factory()->create(['mobile' => '09121234567']);

    $this->post('/login', ['mobile' => '09121234567', 'password' => 'wrong-password']);

    expect(AuditLog::query()->count())->toBe(0);
});

test('logout writes a logout entry for the user who signed out', function () {
    $user = User::factory()->role(StaffRole::Admin)->create();

    $this->actingAs($user)->post('/logout');

    $log = AuditLog::query()->sole();

    expect($log->action)->toBe('logout')
        ->and($log->actor_id)->toBe($user->id);
});

test('a password change writes an entry with no changes, so the password is never stored', function () {
    $user = User::factory()->role(StaffRole::Admin)->mustChangePassword()->create();

    $this->actingAs($user)->post('/password/change', [
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $log = AuditLog::query()->where('action', 'password_change')->sole();

    expect($log->changes)->toBeNull()
        ->and($log->subject_id)->toBe($user->id);

    $this->assertDatabaseMissing('audit_logs', ['changes' => 'a-brand-new-password']);
});
