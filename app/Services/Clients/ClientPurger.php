<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\AuditAction;
use App\Models\Client;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hard delete of one client, after the clinic's retention period (spec section 10.10). Only the command
 * clients:purge calls this. It is the one place where payments and measurement sets are changed (reverses_id and
 * supersedes_id are cleared so the rows can be removed). Everything is removed in one transaction, and an audit row
 * with the row counts is written first. The audit row keeps no personal data.
 */
final class ClientPurger
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * What a purge would remove, per table. Throws when the client may not be purged yet.
     *
     * @return array<string, int>
     */
    public function plan(int $clientId): array
    {
        $client = Client::withTrashed()->find($clientId);

        if (! $client instanceof Client) {
            throw new RuntimeException("No client with id {$clientId}.");
        }

        $deletedAt = $client->getAttribute('deleted_at');

        if ($deletedAt === null) {
            throw new RuntimeException('Soft-delete the client first. Only a deleted client can be purged.');
        }

        $days = config('clinic.retention_days');

        if (! is_int($days) || $days < 1) {
            throw new RuntimeException('CLINIC_RETENTION_DAYS is not set. The clinic must set its retention period before any purge.');
        }

        $eligibleFrom = CarbonImmutable::instance($deletedAt)->addDays($days);

        if (CarbonImmutable::now('Asia/Tehran')->lessThan($eligibleFrom)) {
            throw new RuntimeException('The retention period has not passed. Eligible from '.$eligibleFrom->toDateString().'.');
        }

        $mobile = (string) $client->getAttribute('mobile');
        $setIds = DB::table('measurement_sets')->where('client_id', $clientId)->pluck('id')->all();

        return [
            'appointment_status_history' => DB::table('appointment_status_history')
                ->whereIn('appointment_id', DB::table('appointments')->select('id')->where('client_id', $clientId))
                ->count(),
            'payments' => DB::table('payments')->where('client_id', $clientId)->count(),
            'device_sessions' => DB::table('device_sessions')->where('client_id', $clientId)->count(),
            'measurements' => $setIds === [] ? 0 : DB::table('measurements')->whereIn('measurement_set_id', $setIds)->count(),
            'measurement_sets' => count($setIds),
            'specialist_notes' => DB::table('specialist_notes')->where('client_id', $clientId)->count(),
            'client_medical' => DB::table('client_medical')->where('client_id', $clientId)->count(),
            'appointments' => DB::table('appointments')->where('client_id', $clientId)->count(),
            'session_packages' => DB::table('session_packages')->where('client_id', $clientId)->count(),
            'otp_codes' => $mobile === '' ? 0 : DB::table('otp_codes')->where('mobile', $mobile)->count(),
            'sms_log' => $mobile === '' ? 0 : DB::table('sms_log')->where('mobile', $mobile)->count(),
            'clients' => 1,
        ];
    }

    /**
     * Removes the client and everything that belongs to them. Returns the plan that was carried out.
     *
     * @return array<string, int>
     */
    public function purge(int $clientId): array
    {
        $plan = $this->plan($clientId);
        $client = Client::withTrashed()->findOrFail($clientId);
        $mobile = (string) $client->getAttribute('mobile');

        DB::transaction(function () use ($client, $clientId, $plan, $mobile): void {
            $this->audit->log(AuditAction::Delete, $client, $clientId, ['purged' => $plan]);

            $setIds = DB::table('measurement_sets')->where('client_id', $clientId)->pluck('id')->all();
            $appointmentIds = DB::table('appointments')->where('client_id', $clientId)->pluck('id')->all();

            // Child rows first. Self references are cleared before the rows they point at are deleted.
            DB::table('appointment_status_history')->whereIn('appointment_id', $appointmentIds)->delete();
            DB::table('device_sessions')->where('client_id', $clientId)->delete();
            DB::table('payments')->where('client_id', $clientId)->update(['reverses_id' => null]);
            DB::table('payments')->where('client_id', $clientId)->delete();

            if ($setIds !== []) {
                DB::table('measurements')->whereIn('measurement_set_id', $setIds)->delete();
            }

            DB::table('measurement_sets')->where('client_id', $clientId)->update(['supersedes_id' => null]);
            DB::table('measurement_sets')->where('client_id', $clientId)->delete();
            DB::table('specialist_notes')->where('client_id', $clientId)->delete();
            DB::table('client_medical')->where('client_id', $clientId)->delete();
            DB::table('appointments')->where('client_id', $clientId)->delete();
            DB::table('session_packages')->where('client_id', $clientId)->delete();

            if ($mobile !== '') {
                DB::table('otp_codes')->where('mobile', $mobile)->delete();
                DB::table('sms_log')->where('mobile', $mobile)->delete();
            }

            DB::table('clients')->where('id', $clientId)->delete();
        });

        return $plan;
    }
}
