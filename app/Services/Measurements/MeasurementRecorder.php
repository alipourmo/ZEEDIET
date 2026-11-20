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
 * Records one measurement set (spec sections 6.4, 7.4 and 8.3). The operator's set is pending_review: a specialist
 * reviews and publishes it. Out-of-range values are rejected, never clamped (spec 7.4).
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
    public function record(Client $client, User $actor, CarbonImmutable $measuredAt, array $values, ?string $note = null): MeasurementSet
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
                $value = (float) $text;

                if ($value < (float) $range['min'] || $value > (float) $range['max']) {
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

        return DB::transaction(function () use ($client, $actor, $measuredAt, $note, $parsed, $types): MeasurementSet {
            /** @var MeasurementSet $set */
            $set = MeasurementSet::query()->create([
                'client_id' => $client->getKey(),
                'measured_at' => $measuredAt,
                'recorded_by' => $actor->getKey(),
                'status' => MeasurementSetStatus::PendingReview,
                'note' => $note === null || trim($note) === '' ? null : trim($note),
            ]);

            foreach ($parsed as $key => $value) {
                Measurement::query()->create([
                    'measurement_set_id' => $set->getKey(),
                    'measurement_type_id' => $types->get($key)?->getKey(),
                    'value' => $value,
                ]);
            }

            // The audit row names the types, never the values (spec 7.8: no medical text in the log).
            $this->audit->log(AuditAction::Create, $set, (int) $client->getKey(), ['new' => [
                'status' => MeasurementSetStatus::PendingReview->value,
                'types' => array_keys($parsed),
            ]], $actor);

            return $set;
        });
    }
}
