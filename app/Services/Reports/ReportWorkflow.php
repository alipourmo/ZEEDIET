<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AuditAction;
use App\Enums\MeasurementSetStatus;
use App\Events\ReportPublished;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Measurements\MeasurementRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The specialist's report workflow (spec sections 7.4 and 8.4): publish, return to the operator, and correct a published
 * report. Every action needs measurement.review, checked here as well as on the route, so a service caller cannot skip it.
 */
final class ReportWorkflow
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MeasurementRecorder $recorder,
    ) {}

    /**
     * Publishes a pending set. If it corrects an older set, the older set gets superseded_at in the same transaction.
     */
    public function publish(MeasurementSet $set, User $reviewer): MeasurementSet
    {
        $this->authorize($reviewer);

        $published = DB::transaction(function () use ($set, $reviewer): MeasurementSet {
            /** @var MeasurementSet $current */
            $current = MeasurementSet::query()->whereKey($set->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($current);

            if ($current->measurements()->doesntExist()) {
                throw ValidationException::withMessages(['values' => __('measurements.at_least_one')]);
            }

            $now = CarbonImmutable::now('Asia/Tehran');
            $supersedes = $current->getAttribute('supersedes_id');

            $current->setAttribute('status', MeasurementSetStatus::Published);
            $current->setAttribute('reviewed_by', $reviewer->getKey());
            $current->setAttribute('published_at', $now);
            $current->save();

            if ($supersedes !== null) {
                /** @var MeasurementSet $old */
                $old = MeasurementSet::query()->whereKey($supersedes)->lockForUpdate()->firstOrFail();
                $old->setAttribute('superseded_at', $now);
                $old->save();
            }

            $this->audit->log(AuditAction::Publish, $current, (int) $current->client_id, ['new' => [
                'status' => MeasurementSetStatus::Published->value,
                'supersedes_id' => $supersedes,
            ]], $reviewer);

            return $current;
        });

        event(new ReportPublished($published));

        return $published;
    }

    /**
     * Sends a pending set back to the operator. The status stays pending_review; the comment is stored in note (spec 8.4).
     */
    public function returnToOperator(MeasurementSet $set, User $reviewer, string $comment): MeasurementSet
    {
        $this->authorize($reviewer);

        $text = trim($comment);

        if ($text === '') {
            throw ValidationException::withMessages(['comment' => __('reports.comment_required')]);
        }

        return DB::transaction(function () use ($set, $reviewer, $text): MeasurementSet {
            /** @var MeasurementSet $current */
            $current = MeasurementSet::query()->whereKey($set->getKey())->lockForUpdate()->firstOrFail();
            $this->assertPending($current);

            $current->setAttribute('note', $text);
            $current->save();

            // The audit row says the set was returned. The comment itself is not logged (it may name medical detail).
            $this->audit->log(AuditAction::Update, $current, (int) $current->client_id, ['new' => ['returned' => true]], $reviewer);

            return $current;
        });
    }

    /**
     * Opens a correction of a published, current set: a new pending set that supersedes it (spec 7.4). Only one
     * correction may be pending for a set at a time.
     *
     * @param  array<string, mixed>  $values
     */
    public function correct(MeasurementSet $published, User $reviewer, array $values, ?string $note = null): MeasurementSet
    {
        $this->authorize($reviewer);

        return DB::transaction(function () use ($published, $reviewer, $values, $note): MeasurementSet {
            /** @var MeasurementSet $target */
            $target = MeasurementSet::query()->whereKey($published->getKey())->lockForUpdate()->firstOrFail();

            if ($target->currentStatus() !== MeasurementSetStatus::Published || $target->getAttribute('superseded_at') !== null) {
                throw ValidationException::withMessages(['status' => __('reports.not_correctable')]);
            }

            $pending = MeasurementSet::query()
                ->where('supersedes_id', $target->getKey())
                ->where('status', MeasurementSetStatus::PendingReview->value)
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages(['status' => __('reports.correction_pending')]);
            }

            /** @var Client $client */
            $client = Client::query()->findOrFail($target->client_id);

            // The date cast is a Carbon at runtime, but Larastan reads it as a string, so the type is checked here.
            $measured = $target->getAttribute('measured_at');
            $measuredAt = $measured instanceof \DateTimeInterface
                ? CarbonImmutable::instance($measured)
                : CarbonImmutable::parse((string) $measured, 'Asia/Tehran');

            return $this->recorder->record(
                $client,
                $reviewer,
                $measuredAt,
                $values,
                $note,
                (int) $target->getKey(),
            );
        });
    }

    private function assertPending(MeasurementSet $set): void
    {
        if ($set->currentStatus() !== MeasurementSetStatus::PendingReview) {
            throw ValidationException::withMessages(['status' => __('reports.not_pending')]);
        }
    }

    private function authorize(User $actor): void
    {
        if (! Gate::forUser($actor)->allows('measurement.review')) {
            throw new AuthorizationException;
        }
    }
}
