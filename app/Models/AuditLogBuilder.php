<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Query builder for audit_logs that refuses bulk updates and deletes (spec section 6.5).
 * Model-level update() and delete() reach these methods too, so they are refused as well.
 */
class AuditLogBuilder extends Builder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): int
    {
        throw new LogicException('Audit log entries are append-only and cannot be updated.');
    }

    public function delete(): mixed
    {
        throw new LogicException('Audit log entries are append-only and cannot be deleted.');
    }
}
