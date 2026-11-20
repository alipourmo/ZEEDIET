<?php

declare(strict_types=1);

namespace App\Services\Operator;

use App\Models\DeviceSession;
use App\Models\MeasurementSet;
use App\Models\User;
use App\Support\Digits;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The operator's own entries from the last seven days, read-only (spec sections 8.3 and 5: measurement.view shows own
 * entries for the operator). Covers device sessions and measurement sets. Client names only: no phone numbers and no
 * medical text.
 */
final class MyEntries
{
    public const DAYS = 7;

    /**
     * Newest first.
     *
     * @return list<array{at: CarbonInterface, client: string, kind: string, detail: string, status: string}>
     */
    public function forOperator(User $operator, CarbonImmutable $now): array
    {
        $from = $now->startOfDay()->subDays(self::DAYS - 1);
        $rows = [];

        $sessions = DeviceSession::query()
            ->with(['client:id,first_name,last_name', 'device:id,name'])
            ->where('operator_id', $operator->getKey())
            ->where('performed_at', '>=', $from->format('Y-m-d H:i:s'))
            ->get();

        foreach ($sessions as $session) {
            $rows[] = [
                'at' => $session->performed_at,
                'client' => (string) $session->client?->fullName(),
                'kind' => __('operator.kind_session'),
                'detail' => __('operator.session_detail', [
                    'number' => Digits::toPersian($session->session_number),
                    'device' => (string) $session->device?->name,
                ]),
                'status' => __('operator.status_done'),
            ];
        }

        $sets = MeasurementSet::query()
            ->with(['client:id,first_name,last_name', 'measurements.type'])
            ->where('recorded_by', $operator->getKey())
            ->where('created_at', '>=', $from->format('Y-m-d H:i:s'))
            ->get();

        foreach ($sets as $set) {
            $parts = [];

            foreach ($set->measurements as $measurement) {
                $parts[] = __((string) $measurement->type?->label_key).' '.Digits::toPersian((string) $measurement->value).' '.$measurement->type?->unit;
            }

            $rows[] = [
                'at' => $set->created_at,
                'client' => (string) $set->client?->fullName(),
                'kind' => __('operator.kind_measurement'),
                'detail' => implode('، ', $parts),
                'status' => $set->status->value === 'published'
                    ? __('operator.status_published')
                    : __('operator.status_pending'),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $rows;
    }
}
