<?php

declare(strict_types=1);

namespace App\Services\Measurements;

use App\Enums\AuditAction;
use App\Enums\MeasurementSetStatus;
use App\Models\Client;
use App\Models\Measurement;
use App\Models\MeasurementSet;
use App\Models\MeasurementType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Digits;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records and changes measurement sets (spec sections 6.4, 7.4 and 8.3). The operator's set is pending_review: a
 * specialist reviews and publishes it (ReportWorkflow). Out-of-range values are rejected, never clamped (spec 7.4).
 */
final class MeasurementRecorder
{
    /**
     * The types the operator's form offers: weight and circumferences (spec 8.3). Body fat and muscle mass are
     * measured by the specialist.
     */
    public const OPERATOR_KEYS = ['weight', 'waist', 'abdomen', 'upper_arm', 'thigh', 'hip', 'chest', 'neck'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return Collection<int, MeasurementType>
     */
    public function operatorTypes(): Collection
    {
        return MeasurementType::query()
            ->where('is_active', true)
            ->whereIn('key', self::OPERATOR_KEYS)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $values  keyed by measurement type key; blank values are skipped
     *
     * @throws ValidationException when no value is given, or a value is invalid or out of range
     */
    public function record(Client $client, User $actor, CarbonImmutable $measuredAt, array $values, ?string $note = null, ?int $supersedesId = null): MeasurementSet
    {
        $parsed = $this->parseValues($values);

        return DB::transaction(function () use ($client, $actor, $measuredAt, $note, $parsed, $supersedesId): MeasurementSet {
            /** @var MeasurementSet $set */
            $set = MeasurementSet::query()->create([
                'client_id' => $client->getKey(),
                'measured_at' => $measuredAt,
                'recorded_by' => $actor->getKey(),
                'status' => MeasurementSetStatus::PendingReview,
                'supersedes_id' => $supersedesId,
                'note' => $this->note($note),
            ]);

            $this->writeValues($set, $parsed['values'], $parsed['types']);

            // The audit row names the types, never the values (spec 7.8: no medical text in the log).
            $this->audit->log(AuditAction::Create, $set, (int) $client->getKey(), ['new' => [
                'status' => MeasurementSetStatus::PendingReview->value,
                'types' => array_keys($parsed['values']),
                'supersedes_id' => $supersedesId,
            ]], $actor);

            return $set;
        });
    }

    /**
     * Replaces the values of a set that is still pending review (the specialist's edit before publish, spec 8.4).
     * A published set cannot be changed (MeasurementSet refuses it).
     *
     * @param  array<string, mixed>  $values
     */
    public function replaceValues(MeasurementSet $set, User $actor, array $values): MeasurementSet
    {
        $parsed = $this->parseValues($values);

        return DB::transaction(function () use ($set, $actor, $parsed): MeasurementSet {
            /** @var MeasurementSet $current */
            $current = MeasurementSet::query()->whereKey($set->getKey())->lockForUpdate()->firstOrFail();

            if ($current->currentStatus() !== MeasurementSetStatus::PendingReview) {
                throw ValidationException::withMessages(['status' => __('reports.not_pending')]);
            }

            Measurement::query()->where('measurement_set_id', $current->getKey())->delete();
            $this->writeValues($current, $parsed['values'], $parsed['types']);

            $this->audit->log(AuditAction::Update, $current, (int) $current->client_id, [
                'new' => ['types' => array_keys($parsed['values'])],
            ], $actor);

            return $current;
        });
    }

    /**
     * Checks the values against the types and their unit ranges. Returns the types and the decimal strings to store.
     *
     * @param  array<string, mixed>  $values
     * @return array{types: Collection<string, MeasurementType>, values: array<string, string>}
     *
     * @throws ValidationException
     */
    public function parseValues(array $values): array
    {
        $filled = [];

        foreach ($values as $key => $raw) {
            $text = is_string($raw) ? trim($raw) : '';

            if ($text !== '') {
                $filled[(string) $key] = $text;
            }
        }

        if ($filled === []) {
            throw ValidationException::withMessages(['values' => __('measurements.at_least_one')]);
        }

        /** @var Collection<string, MeasurementType> $types */
        $types = MeasurementType::query()
            ->where('is_active', true)
            ->whereIn('key', array_keys($filled))
            ->get()
            ->keyBy('key');

        $errors = [];
        $parsed = [];

        foreach ($filled as $key => $text) {
            $field = 'values.'.$key;
            $type = $types->get($key);

            if ($type === null) {
                $errors[$field] = __('measurements.unknown');

                continue;
            }

            $label = __((string) $type->getAttribute('label_key'));

            if (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $text) !== 1) {
                $errors[$field] = __('measurements.invalid', ['label' => $label]);

                continue;
            }

            $unit = (string) $type->getAttribute('unit');
            $range = config('clinic.measurement_ranges.'.$unit);

            if (is_array($range)) {
                $number = (float) $text;

                if ($number < (float) $range['min'] || $number > (float) $range['max']) {
                    $errors[$field] = __('measurements.out_of_range', [
                        'label' => $label,
                        'min' => Digits::toPersian($range['min']),
                        'max' => Digits::toPersian($range['max']),
                        'unit' => $unit,
                    ]);

                    continue;
                }
            }

            $parsed[$key] = number_format((float) $text, 2, '.', '');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['types' => $types, 'values' => $parsed];
    }

    /**
     * @param  array<string, string>  $values
     * @param  Collection<string, MeasurementType>  $types
     */
    private function writeValues(MeasurementSet $set, array $values, Collection $types): void
    {
        foreach ($values as $key => $value) {
            Measurement::query()->create([
                'measurement_set_id' => $set->getKey(),
                'measurement_type_id' => $types->get($key)?->getKey(),
                'value' => $value,
            ]);
        }
    }

    private function note(?string $note): ?string
    {
        $trimmed = $note === null ? '' : trim($note);

        return $trimmed === '' ? null : $trimmed;
    }
}
