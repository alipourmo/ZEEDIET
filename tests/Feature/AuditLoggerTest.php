<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;

test('a log entry records the signed-in user as actor, plus the subject and client', function () {
    $actor = User::factory()->role(StaffRole::Secretary)->create();
    $subject = User::factory()->create();

    $this->actingAs($actor);
    app(AuditLogger::class)->log(AuditAction::Update, $subject, 7, ['old' => ['name' => 'A'], 'new' => ['name' => 'B']]);

    $log = AuditLog::query()->sole();

    expect($log->actor_type)->toBe('user')
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->action)->toBe('update')
        ->and($log->subject_type)->toBe(User::class)
        ->and($log->subject_id)->toBe($subject->id)
        ->and($log->client_id)->toBe(7)
        ->and($log->changes)->toBe(['old' => ['name' => 'A'], 'new' => ['name' => 'B']])
        ->and($log->created_at)->not->toBeNull();
});

test('with nobody signed in, the actor is the system', function () {
    $subject = User::factory()->create();

    app(AuditLogger::class)->log(AuditAction::Create, $subject);

    $log = AuditLog::query()->sole();

    expect($log->actor_type)->toBe('system')
        ->and($log->actor_id)->toBeNull()
        ->and($log->client_id)->toBeNull()
        ->and($log->changes)->toBeNull();
});

test('passwords and tokens are removed from changes, at any depth', function () {
    $subject = User::factory()->create();

    app(AuditLogger::class)->log(AuditAction::Update, $subject, null, [
        'old' => ['password' => 'old-secret', 'name' => 'A'],
        'new' => ['remember_token' => 'token-value', 'api_token' => 'x', 'name' => 'B', 'nested' => ['client_secret' => 's', 'note' => 'ok']],
    ]);

    $log = AuditLog::query()->sole();

    expect($log->changes)->toBe([
        'old' => ['name' => 'A'],
        'new' => ['name' => 'B', 'nested' => ['note' => 'ok']],
    ]);
});

test('an action outside the spec list cannot be logged', function () {
    expect(fn () => AuditAction::from('delete_everything'))->toThrow(ValueError::class);
});
