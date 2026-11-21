<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes audit entries (spec sections 6.5 and 7.8).
 *
 * Services and controllers call log() explicitly. Model observers are not used, because they miss bulk updates.
 * Tests assert the entries.
 */
final class AuditLogger
{
    /** Keys that are never written to audit_logs.changes (spec section 6.5). */
    private const SECRET_KEY_PATTERN = '/password|token|secret/i';

    /**
     * @param  Model  $subject  The record the action is about. Must already be saved.
     * @param  int|null  $clientId  Set for anything about a client, so the per-client history can be shown.
     * @param  array<string, mixed>|null  $changes  Usually ['old' => [...], 'new' => [...]].
     * @param  User|null  $actor  The staff member. Defaults to the signed-in user, or 'system' when nobody is signed in.
     */
    public function log(
        AuditAction $action,
        Model $subject,
        ?int $clientId = null,
        ?array $changes = null,
        ?User $actor = null,
    ): AuditLog {
        $signedIn = Auth::user();
        $actor ??= $signedIn instanceof User ? $signedIn : null;

        return $this->write(
            $action,
            $subject,
            $clientId,
            $changes,
            $actor instanceof User ? 'user' : 'system',
            $actor?->getKey(),
        );
    }

    /**
     * An action taken by a client in the portal (spec 7.8: login and logout of clients). The actor is the client.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public function logAsClient(AuditAction $action, Model $subject, Client $client, ?array $changes = null): AuditLog
    {
        return $this->write($action, $subject, (int) $client->getKey(), $changes, 'client', (int) $client->getKey());
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function write(
        AuditAction $action,
        Model $subject,
        ?int $clientId,
        ?array $changes,
        string $actorType,
        int|string|null $actorId,
    ): AuditLog {
        $request = request();

        /** @var AuditLog $log */
        $log = AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'client_id' => $clientId,
            'changes' => $changes === null ? null : $this->withoutSecrets($changes),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
        ]);

        return $log;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function withoutSecrets(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEY_PATTERN, $key) === 1) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->withoutSecrets($value) : $value;
        }

        return $clean;
    }
}
