<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\MeasurementSetStatus;
use App\Models\MeasurementSet;
use App\Models\MeasurementType;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * What the specialist's inbox and review page show (spec 8.4): pending sets oldest first, and the previous values to
 * compare against. Read-only; the state changes are in ReportWorkflow.
 */
final class ReviewQueue
{
    /**
     * Pending sets, oldest first, so the earliest report is reviewed first.
     *
     * @return Collection<int, MeasurementSet>
     */
    public function pending(): Collection
    {
        return MeasurementSet::query()
            ->where('status', MeasurementSetStatus::PendingReview->value)
            ->with(['client', 'recorder'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The values to compare against: the set this one corrects, or else the latest current published set of the client.
     */
    public function previousFor(MeasurementSet $set): ?MeasurementSet
    {
        $supersedes = $set->getAttribute('supersedes_id');

        if ($supersedes !== null) {
            return MeasurementSet::query()->with('measurements.type')->find($supersedes);
        }

        return MeasurementSet::query()
            ->current()
            ->where('client_id', $set->client_id)
            ->whereKeyNot($set->getKey())
            ->with('measurements.type')
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * One row per active measurement type, in display order. Values are the stored decimal strings; the change is
     * current minus previous with a sign (Decimal::difference), or null when either side is missing.
     *
     * @return list<array{key: string, label: string, unit: string, previous: ?string, current: ?string, change: ?string}>
     */
    public function comparison(MeasurementSet $set, ?MeasurementSet $previous): array
    {
        $current = $set->valuesByKey();
        $before = $previous === null ? [] : $previous->valuesByKey();

        $rows = [];

        foreach (MeasurementType::query()->where('is_active', true)->orderBy('sort_order')->get() as $type) {
            $key = (string) $type->getAttribute('key');
            $now = $current[$key] ?? null;
            $was = $before[$key] ?? null;

            $label = __((string) $type->getAttribute('label_key'));

            $rows[] = [
                'key' => $key,
                'label' => is_string($label) ? $label : $key,
                'unit' => (string) $type->getAttribute('unit'),
                'previous' => $was,
                'current' => $now,
                'change' => Decimal::difference($was, $now),
            ];
        }

        return $rows;
    }
}
