<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use App\Enums\AuditAction;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Session packages (spec sections 6.3 and 8.2). A package is created, never edited or deleted here: a package is a
 * financial record. Every create is audited.
 */
final class PackageService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{client_id: int, service_id: int, total_sessions: int, price: ?int, purchased_at: string}  $data
     */
    public function create(array $data, User $actor): SessionPackage
    {
        return DB::transaction(function () use ($data, $actor): SessionPackage {
            /** @var SessionPackage $package */
            $package = SessionPackage::query()->create($data + ['created_by' => $actor->getKey()]);

            $this->audit->log(
                AuditAction::Create,
                $package,
                (int) $package->getAttribute('client_id'),
                ['new' => [
                    'service_id' => $data['service_id'],
                    'total_sessions' => $data['total_sessions'],
                    'price' => $data['price'],
                    'purchased_at' => $data['purchased_at'],
                ]],
                $actor,
            );

            return $package;
        });
    }
}
