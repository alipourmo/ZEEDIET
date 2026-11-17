<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * An append-only audit entry (spec sections 6.5 and 7.8). Rows are created, never changed or removed.
 *
 * Writes go through App\Services\Audit\AuditLogger (added in S1-T3). The model refuses updates and deletes,
 * so an accidental update or delete in code fails loudly.
 */
class AuditLog extends Model
{
    /** There is no updated_at column. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'client_id',
        'changes',
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'subject_id' => 'integer',
            'client_id' => 'integer',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit log entries are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit log entries are append-only and cannot be deleted.');
        });
    }
}
