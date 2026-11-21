<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\MeasurementSetStatus;
use App\Enums\StaffRole;
use App\Events\ReportPublished;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Measurement;
use App\Models\MeasurementSet;
use App\Models\MeasurementType;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Reports\ReportWorkflow;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(MeasurementTypeSeeder::class);
});

function repUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

/**
 * A pending set recorded by an operator, for the given client.
 *
 * @param  array<string, string>  $values
 */
function repPending(array $values = ['weight' => '72.5', 'waist' => '88'], ?Client $client = null): MeasurementSet
{
    return app(MeasurementRecorder::class)->record(
        $client ?? makeClient(),
        repUser(StaffRole::Operator),
        CarbonImmutable::now('Asia/Tehran')->startOfDay(),
        $values,
    );
}

function repPublished(array $values = ['weight' => '72.5', 'waist' => '88'], ?Client $client = null): MeasurementSet
{
    return app(ReportWorkflow::class)->publish(repPending($values, $client), repUser(StaffRole::Specialist));
}

test('publishing a pending set makes it published, audits it and dispatches ReportPublished', function () {
    Event::fake([ReportPublished::class]);
    $specialist = repUser(StaffRole::Specialist);
    $set = repPending();

    $published = app(ReportWorkflow::class)->publish($set, $specialist);

    expect($published->currentStatus())->toBe(MeasurementSetStatus::Published)
        ->and($published->reviewed_by)->toBe($specialist->getKey())
        ->and($published->published_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::Publish->value)->where('subject_id', $set->getKey())->exists())->toBeTrue();

    Event::assertDispatched(ReportPublished::class, fn (ReportPublished $event): bool => $event->set->getKey() === $set->getKey());
});

test('a published set is current; a pending set is not, so no client route or chart can show it', function () {
    $client = makeClient();
    $pending = repPending(client: $client);

    expect(MeasurementSet::query()->current()->where('client_id', $client->getKey())->count())->toBe(0);

    app(ReportWorkflow::class)->publish($pending, repUser(StaffRole::Specialist));

    expect(MeasurementSet::query()->current()->where('client_id', $client->getKey())->pluck('id')->all())
        ->toBe([$pending->getKey()]);
});

test('only measurement.review holders can publish, return or correct', function (StaffRole $role) {
    $set = repPending();
    $workflow = app(ReportWorkflow::class);
    $actor = repUser($role);

    expect(fn () => $workflow->publish($set, $actor))->toThrow(AuthorizationException::class)
        ->and(fn () => $workflow->returnToOperator($set, $actor, 'اصلاح شود'))->toThrow(AuthorizationException::class)
        ->and(fn () => $workflow->correct(repPublished(), $actor, ['weight' => '70']))->toThrow(AuthorizationException::class)
        ->and($set->fresh()?->currentStatus())->toBe(MeasurementSetStatus::PendingReview);
})->with([
    'operator' => StaffRole::Operator,
    'secretary' => StaffRole::Secretary,
]);

test('admin and specialist can publish', function (StaffRole $role) {
    $set = repPending();

    expect(app(ReportWorkflow::class)->publish($set, repUser($role))->currentStatus())->toBe(MeasurementSetStatus::Published);
})->with([
    'admin' => StaffRole::Admin,
    'specialist' => StaffRole::Specialist,
]);

test('a published set cannot be edited: status, note and values are refused', function () {
    $set = repPublished();

    expect(function () use ($set) {
        $set->note = 'تغییر';
        $set->save();
    })->toThrow(LogicException::class)
        ->and(fn () => $set->update(['measured_at' => '2020-01-01']))->toThrow(LogicException::class);

    $measurement = Measurement::query()->where('measurement_set_id', $set->getKey())->firstOrFail();

    expect(fn () => $measurement->update(['value' => '1.00']))->toThrow(LogicException::class)
        ->and(fn () => $measurement->delete())->toThrow(LogicException::class)
        ->and(fn () => Measurement::query()->create([
            'measurement_set_id' => $set->getKey(),
            'measurement_type_id' => MeasurementType::query()->where('key', 'hip')->value('id'),
            'value' => '90.00',
        ]))->toThrow(LogicException::class);
});

test('replacing the values of a published set is refused by the service too', function () {
    $set = repPublished();

    expect(fn () => app(MeasurementRecorder::class)->replaceValues($set, repUser(StaffRole::Specialist), ['weight' => '60']))
        ->toThrow(ValidationException::class);

    $weight = Measurement::query()->where('measurement_set_id', $set->getKey())->with('type')->get()
        ->first(fn (Measurement $m): bool => $m->type?->key === 'weight');

    expect((string) $weight?->value)->toBe('72.50');
});

test('the specialist can edit the values of a pending set before publishing', function () {
    $set = repPending();
    $specialist = repUser(StaffRole::Specialist);

    app(MeasurementRecorder::class)->replaceValues($set, $specialist, ['weight' => '71', 'body_fat_percent' => '18.5']);

    $values = $set->measurements()->with('type')->get()->mapWithKeys(fn (Measurement $m): array => [(string) $m->type?->key => (string) $m->value])->all();

    expect($values)->toBe(['weight' => '71.00', 'body_fat_percent' => '18.50']);
});

test('returning a set keeps it pending, stores the comment in note and does not publish', function () {
    $set = repPending();

    $returned = app(ReportWorkflow::class)->returnToOperator($set, repUser(StaffRole::Specialist), '  دور کمر دوباره اندازه بگیرید  ');

    expect($returned->currentStatus())->toBe(MeasurementSetStatus::PendingReview)
        ->and($returned->note)->toBe('دور کمر دوباره اندازه بگیرید')
        ->and($returned->published_at)->toBeNull();
});

test('a return without a comment is refused', function () {
    $set = repPending();

    expect(fn () => app(ReportWorkflow::class)->returnToOperator($set, repUser(StaffRole::Specialist), '   '))
        ->toThrow(ValidationException::class);
});

test('publishing a set that is no longer pending is refused, so a double publish writes nothing twice', function () {
    $set = repPending();
    $specialist = repUser(StaffRole::Specialist);
    app(ReportWorkflow::class)->publish($set, $specialist);

    expect(fn () => app(ReportWorkflow::class)->publish($set, $specialist))->toThrow(ValidationException::class)
        ->and(AuditLog::query()->where('action', AuditAction::Publish->value)->where('subject_id', $set->getKey())->count())->toBe(1);
});

test('a correction is a new pending set that supersedes the published one; the old set stays current until the correction is published', function () {
    $client = makeClient();
    $original = repPublished(['weight' => '72.5'], $client);
    $workflow = app(ReportWorkflow::class);
    $specialist = repUser(StaffRole::Specialist);

    $correction = $workflow->correct($original, $specialist, ['weight' => '70.0'], 'اصلاح خطای ورود');

    expect($correction->currentStatus())->toBe(MeasurementSetStatus::PendingReview)
        ->and($correction->supersedes_id)->toBe($original->getKey())
        ->and($original->fresh()?->superseded_at)->toBeNull()
        ->and(MeasurementSet::query()->current()->where('client_id', $client->getKey())->pluck('id')->all())->toBe([$original->getKey()]);

    $workflow->publish($correction, $specialist);

    expect($original->fresh()?->superseded_at)->not->toBeNull()
        ->and(MeasurementSet::query()->current()->where('client_id', $client->getKey())->pluck('id')->all())->toBe([$correction->getKey()]);
});

test('the original published values are unchanged by a correction', function () {
    $original = repPublished(['weight' => '72.5']);

    app(ReportWorkflow::class)->correct($original, repUser(StaffRole::Specialist), ['weight' => '70']);

    $values = Measurement::query()->where('measurement_set_id', $original->getKey())->with('type')->get();

    expect($values->count())->toBe(1)
        ->and((string) $values->first()?->value)->toBe('72.50');
});

test('only one correction can be pending for a set at a time', function () {
    $original = repPublished();
    $workflow = app(ReportWorkflow::class);
    $specialist = repUser(StaffRole::Specialist);
    $workflow->correct($original, $specialist, ['weight' => '70']);

    expect(fn () => $workflow->correct($original, $specialist, ['weight' => '69']))->toThrow(ValidationException::class);
});

test('a superseded or pending set cannot be corrected', function () {
    $workflow = app(ReportWorkflow::class);
    $specialist = repUser(StaffRole::Specialist);
    $original = repPublished();
    $correction = $workflow->correct($original, $specialist, ['weight' => '70']);
    $workflow->publish($correction, $specialist);

    expect(fn () => $workflow->correct($original->fresh(), $specialist, ['weight' => '69']))->toThrow(ValidationException::class)
        ->and(fn () => $workflow->correct(repPending(), $specialist, ['weight' => '69']))->toThrow(ValidationException::class);
});

test('a correction is validated like any set: out of range is refused and nothing is written', function () {
    $original = repPublished();

    expect(fn () => app(ReportWorkflow::class)->correct($original, repUser(StaffRole::Specialist), ['weight' => '900']))
        ->toThrow(ValidationException::class)
        ->and(MeasurementSet::query()->where('supersedes_id', $original->getKey())->exists())->toBeFalse();
});
