<?php

declare(strict_types=1);

use App\Models\AuditLog;

function makeAuditLog(): AuditLog
{
    return AuditLog::create([
        'actor_type' => 'user',
        'actor_id' => 1,
        'action' => 'view',
        'subject_type' => 'client',
        'subject_id' => 7,
        'client_id' => 7,
        'changes' => null,
        'ip' => '127.0.0.1',
        'user_agent' => 'test',
    ]);
}

test('an audit log entry is created with created_at and no updated_at', function () {
    $log = makeAuditLog();

    expect($log->created_at)->not->toBeNull()
        ->and(array_key_exists('updated_at', $log->getAttributes()))->toBeFalse();
});

test('an audit log entry cannot be updated', function () {
    $log = makeAuditLog();

    expect(fn () => $log->update(['action' => 'delete']))->toThrow(LogicException::class);
});

test('an audit log entry cannot be deleted through the model', function () {
    $log = makeAuditLog();

    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(AuditLog::count())->toBe(1);
});

test('an audit log entry cannot be force-deleted', function () {
    $log = makeAuditLog();

    expect(fn () => $log->forceDelete())->toThrow(LogicException::class);
    expect(AuditLog::count())->toBe(1);
});

test('bulk updates and deletes on audit_logs are refused', function () {
    makeAuditLog();

    expect(fn () => AuditLog::query()->update(['action' => 'delete']))->toThrow(LogicException::class);
    expect(fn () => AuditLog::query()->delete())->toThrow(LogicException::class);
    expect(AuditLog::count())->toBe(1);
});
