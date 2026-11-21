<?php

declare(strict_types=1);

namespace App\Services\Specialist;

use App\Enums\AuditAction;
use App\Models\Client;
use App\Models\MeasurementType;
use App\Models\SpecialistNote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Progress\ProgressService;
use App\Support\Display;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * What the specialist's client file shows beyond the summary (spec 8.4): the notes, the next follow-up, the measurement
 * history and the chart. Opening it is audited, because it reads notes and measurements. Note bodies never enter the audit.
 */
final class ClientFile
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProgressService $progress,
    ) {}

    /**
     * @return array{
     *     notes: Collection<int, SpecialistNote>,
     *     followUpOn: ?DateTimeInterface,
     *     summary: array<string, mixed>,
     *     chart: array<string, mixed>,
     *     history: list<array{date: string, values: list<array{label: string, value: string, unit: string}>}>
     * }
     *
     * @throws AuthorizationException
     */
    public function open(Client $client, User $actor): array
    {
        if (! Gate::forUser($actor)->allows('note.manage')) {
            throw new AuthorizationException;
        }

        $this->audit->log(AuditAction::View, $client, (int) $client->getKey(), [
            'sections' => ['notes', 'measurements'],
        ], $actor);

        /** @var Collection<int, SpecialistNote> $notes */
        $notes = SpecialistNote::query()
            ->where('client_id', $client->getKey())
            ->with('specialist')
            ->orderByDesc('id')
            ->get();

        $followUp = $notes->first()?->getAttribute('next_follow_up_on');

        return [
            'notes' => $notes,
            'followUpOn' => $followUp instanceof DateTimeInterface ? $followUp : null,
            'summary' => $this->progress->summary($client),
            'chart' => $this->progress->chart($client),
            'history' => $this->history($client),
        ];
    }

    /**
     * The client's published sets, newest first, with the entered values in display order.
     *
     * @return list<array{date: string, values: list<array{label: string, value: string, unit: string}>}>
     */
    private function history(Client $client): array
    {
        $types = MeasurementType::query()->where('is_active', true)->orderBy('sort_order')->get();

        $rows = [];

        foreach ($this->progress->currentSets($client)->reverse() as $set) {
            $values = $set->valuesByKey();
            $items = [];

            foreach ($types as $type) {
                $key = (string) $type->getAttribute('key');

                if (isset($values[$key])) {
                    $items[] = [
                        'label' => $type->label(),
                        'value' => $values[$key],
                        'unit' => (string) $type->getAttribute('unit'),
                    ];
                }
            }

            $rows[] = [
                'date' => Display::jdate($set->measured_at),
                'values' => $items,
            ];
        }

        return $rows;
    }
}
