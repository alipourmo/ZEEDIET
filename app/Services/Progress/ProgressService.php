<?php

declare(strict_types=1);

namespace App\Services\Progress;

use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\MeasurementType;
use App\Support\Decimal;
use App\Support\Display;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Progress of a client from published measurements (spec sections 7.4 and 11). Only sets that are published and not
 * superseded count, so a correction replaces the value it corrects, and a pending set never shows.
 */
final class ProgressService
{
    /** The series a chart shows by default: weight and the main circumferences (spec 8.4). */
    public const CHART_KEYS = ['weight', 'waist', 'abdomen', 'hip'];

    /** A chart shows at most this many series (spec 11). */
    public const MAX_SERIES = 4;

    /** Weight in the brand colour, circumferences in muted tones (spec 11). */
    private const COLORS = [
        'weight' => '#E5546B',
        'waist' => '#7A6E72',
        'abdomen' => '#A39A9D',
        'hip' => '#5C5256',
    ];

    private const DEFAULT_COLOR = '#7A6E72';

    /**
     * The client's current published sets, oldest first.
     *
     * @return Collection<int, MeasurementSet>
     */
    public function currentSets(Client $client): Collection
    {
        return MeasurementSet::query()
            ->current()
            ->where('client_id', $client->getKey())
            ->with('measurements.type')
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * For each active type: the first and the latest published value, and the change. A type's first value is in the
     * earliest set that has it.
     *
     * @return array{
     *     first: ?MeasurementSet,
     *     latest: ?MeasurementSet,
     *     rows: list<array{key: string, label: string, unit: string, first: ?string, latest: ?string, delta: ?string}>
     * }
     */
    public function summary(Client $client): array
    {
        $sets = $this->currentSets($client);
        $perSet = $this->valuesPerSet($sets);

        $rows = [];

        foreach ($this->activeTypes() as $type) {
            $key = (string) $type->getAttribute('key');

            $values = [];

            foreach ($perSet as $setValues) {
                if (isset($setValues[$key])) {
                    $values[] = $setValues[$key];
                }
            }

            $first = $values[0] ?? null;
            $latest = $values === [] ? null : $values[count($values) - 1];

            $rows[] = [
                'key' => $key,
                'label' => $this->label($type),
                'unit' => (string) $type->getAttribute('unit'),
                'first' => $first,
                'latest' => $latest,
                'delta' => Decimal::difference($first, $latest),
            ];
        }

        return [
            'first' => $sets->first(),
            'latest' => $sets->last(),
            'rows' => $rows,
        ];
    }

    /**
     * Chart data: one label per current set (Jalali date, oldest first) and one series per key, with null where a set
     * has no value for it. Keys over MAX_SERIES are refused.
     *
     * @param  list<string>  $keys
     * @return array{
     *     labels: list<string>,
     *     series: list<array{key: string, label: string, unit: string, color: string, data: list<?float>}>
     * }
     *
     * @throws InvalidArgumentException
     */
    public function chart(Client $client, array $keys = self::CHART_KEYS): array
    {
        if (count($keys) > self::MAX_SERIES) {
            throw new InvalidArgumentException('A chart shows at most '.self::MAX_SERIES.' series.');
        }

        $sets = $this->currentSets($client);
        $perSet = $this->valuesPerSet($sets);

        /** @var Collection<string, MeasurementType> $types */
        $types = MeasurementType::query()
            ->where('is_active', true)
            ->whereIn('key', $keys)
            ->get()
            ->keyBy(fn (MeasurementType $type): string => (string) $type->getAttribute('key'));

        $series = [];

        foreach ($keys as $key) {
            $type = $types->get($key);

            if ($type === null) {
                continue;
            }

            $data = [];

            foreach ($perSet as $setValues) {
                $data[] = isset($setValues[$key]) ? (float) $setValues[$key] : null;
            }

            $series[] = [
                'key' => $key,
                'label' => $this->label($type),
                'unit' => (string) $type->getAttribute('unit'),
                'color' => self::COLORS[$key] ?? self::DEFAULT_COLOR,
                'data' => $data,
            ];
        }

        return [
            'labels' => $sets->map(fn (MeasurementSet $set): string => Display::jdate($set->measured_at))->values()->all(),
            'series' => $series,
        ];
    }

    /**
     * @param  Collection<int, MeasurementSet>  $sets
     * @return list<array<string, string>>
     */
    private function valuesPerSet(Collection $sets): array
    {
        return $sets->map(fn (MeasurementSet $set): array => $set->valuesByKey())->values()->all();
    }

    /**
     * @return Collection<int, MeasurementType>
     */
    private function activeTypes(): Collection
    {
        return MeasurementType::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    private function label(MeasurementType $type): string
    {
        $label = __((string) $type->getAttribute('label_key'));

        return is_string($label) ? $label : (string) $type->getAttribute('key');
    }
}
