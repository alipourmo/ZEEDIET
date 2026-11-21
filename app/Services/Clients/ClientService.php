<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\AuditAction;
use App\Models\Client;
use App\Models\ClientMedical;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Client and medical record operations. Every write and every medical read is audited here (spec section 7.8).
 */
final class ClientService
{
    public function __construct(
        private readonly ClientCodeGenerator $codes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  From StoreClientRequest::clientData()
     */
    public function create(array $data, User $actor): Client
    {
        return DB::transaction(function () use ($data, $actor): Client {
            $client = Client::create($data + [
                'code' => $this->codes->next(),
                'status' => 'active',
                'created_by' => $actor->getKey(),
            ]);

            // Every client has a medical record from the start, so the medical page never has to create one.
            ClientMedical::create([
                'client_id' => $client->getKey(),
                'device_contraindications' => [],
                'updated_by' => $actor->getKey(),
            ]);

            $this->audit->log(AuditAction::Create, $client, $client->getKey(), null, $actor);

            return $client;
        });
    }

    /**
     * Logs only the attributes that changed, with their old and new values.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data, User $actor): Client
    {
        return DB::transaction(function () use ($client, $data, $actor): Client {
            $client->fill($data);
            $dirty = $client->getDirty();

            if ($dirty === []) {
                return $client;
            }

            $old = array_intersect_key($client->getOriginal(), $dirty);
            $client->save();

            $this->audit->log(
                AuditAction::Update,
                $client,
                $client->getKey(),
                ['old' => $old, 'new' => $dirty],
                $actor,
            );

            return $client;
        });
    }

    /**
     * Reading the medical record is audited, so the view itself is an event.
     */
    public function viewMedical(Client $client, User $actor): ClientMedical
    {
        $medical = $client->medical()->firstOrFail();

        $this->audit->log(AuditAction::View, $medical, $client->getKey(), null, $actor);

        return $medical;
    }

    /**
     * The audit entry lists the names of the fields that changed, never their values, so medical text stays out of
     * audit_logs.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMedical(Client $client, array $data, User $actor): ClientMedical
    {
        return DB::transaction(function () use ($client, $data, $actor): ClientMedical {
            $medical = $client->medical()->lockForUpdate()->firstOrFail();
            $medical->fill($data);

            $changedFields = array_keys($medical->getDirty());

            if ($changedFields === []) {
                return $medical;
            }

            $medical->updated_by = $actor->getKey();
            $medical->save();

            $this->audit->log(
                AuditAction::Update,
                $medical,
                $client->getKey(),
                ['changed_fields' => $changedFields],
                $actor,
            );

            return $medical;
        });
    }

    /**
     * Soft delete. The row stays, so the code is never reused and history remains.
     */
    public function delete(Client $client, User $actor): void
    {
        DB::transaction(function () use ($client, $actor): void {
            $this->audit->log(AuditAction::Delete, $client, $client->getKey(), null, $actor);
            $client->delete();
        });
    }
}
