<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\MeasurementSetStatus;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Reports\ReportWorkflow;
use App\Services\Reports\ReviewQueue;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;

beforeEach(function () {
    $this->seed(MeasurementTypeSeeder::class);
});

function revUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

/**
 * @param  array<string, string>  $values
 */
function revPending(array $values = ['weight' => '72.5', 'waist' => '88'], ?Client $client = null, ?CarbonImmutable $measuredAt = null): MeasurementSet
{
    return app(MeasurementRecorder::class)->record(
        $client ?? makeClient(),
        revUser(StaffRole::Operator),
        $measuredAt ?? CarbonImmutable::now('Asia/Tehran')->startOfDay(),
        $values,
    );
}

/**
 * @param  array<string, string>  $values
 */
function revPublished(array $values = ['weight' => '72.5', 'waist' => '88'], ?Client $client = null, ?CarbonImmutable $measuredAt = null): MeasurementSet
{
    return app(ReportWorkflow::class)->publish(revPending($values, $client, $measuredAt), revUser(StaffRole::Specialist));
}

test('the inbox lists only pending sets, oldest first', function () {
    $newer = revPending();
    $older = revPending();
    $published = revPublished();

    $newer->forceFill(['created_at' => CarbonImmutable::now()->subDay()])->save();
    $older->forceFill(['created_at' => CarbonImmutable::now()->subDays(3)])->save();

    $ids = app(ReviewQueue::class)->pending()->pluck('id')->all();

    expect($ids)->toBe([$older->getKey(), $newer->getKey()])
        ->and($ids)->not->toContain($published->getKey());

    $older->load('client');

    $this->actingAs(revUser(StaffRole::Specialist))
        ->get(route('specialist.reviews.index'))
        ->assertOk()
        ->assertSee($older->client->fullName())
        ->assertDontSee('ویرایش مقادیر');
});

test('an empty inbox says so', function () {
    $this->actingAs(revUser(StaffRole::Specialist))
        ->get(route('specialist.reviews.index'))
        ->assertOk()
        ->assertSee(__('reviews.inbox_empty'));
});

test('secretary, operator and a guest cannot reach any review route', function (StaffRole $role) {
    $set = revPending();
    $user = revUser($role);

    $this->actingAs($user)->get(route('specialist.reviews.index'))->assertForbidden();
    $this->actingAs($user)->get(route('specialist.reviews.show', $set))->assertForbidden();
    $this->actingAs($user)->put(route('specialist.reviews.update', $set), ['values' => ['weight' => '70']])->assertForbidden();
    $this->actingAs($user)->post(route('specialist.reviews.publish', $set))->assertForbidden();
    $this->actingAs($user)->post(route('specialist.reviews.return', $set), ['comment' => 'x'])->assertForbidden();

    expect($set->fresh()?->currentStatus())->toBe(MeasurementSetStatus::PendingReview);
})->with([
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
]);

test('a guest is sent to log in', function () {
    $set = revPending();

    $this->get(route('specialist.reviews.index'))->assertRedirect(route('login'));
    $this->get(route('specialist.reviews.show', $set))->assertRedirect(route('login'));
});

test('the review page shows the previous and the current values side by side, in Persian digits', function () {
    $client = makeClient();
    revPublished(['weight' => '70'], $client, CarbonImmutable::now('Asia/Tehran')->subMonth()->startOfDay());
    $set = revPending(['weight' => '72.5'], $client);

    $this->actingAs(revUser(StaffRole::Specialist))
        ->get(route('specialist.reviews.show', $set))
        ->assertOk()
        ->assertSee('۷۰.۰۰')
        ->assertSee('۷۲.۵۰')
        ->assertSee('+۲.۵۰');
});

test('a correction compares against the set it corrects', function () {
    $client = makeClient();
    $original = revPublished(['weight' => '72.5'], $client);
    $correction = app(ReportWorkflow::class)->correct($original, revUser(StaffRole::Specialist), ['weight' => '70']);

    $previous = app(ReviewQueue::class)->previousFor($correction);

    expect($previous?->getKey())->toBe($original->getKey());
});

test('the previous set for a first report is none, and another client is never used', function () {
    $set = revPending();
    revPublished(['weight' => '60'], makeClient());

    expect(app(ReviewQueue::class)->previousFor($set))->toBeNull();
});

test('opening a review writes a view audit row, and a published set is not on the review page', function () {
    $set = revPending();
    $specialist = revUser(StaffRole::Specialist);

    $this->actingAs($specialist)->get(route('specialist.reviews.show', $set))->assertOk();

    expect(AuditLog::query()->where('action', AuditAction::View->value)->where('subject_id', $set->getKey())->count())->toBe(1);

    app(ReportWorkflow::class)->publish($set->fresh(), $specialist);

    $this->actingAs($specialist)->get(route('specialist.reviews.show', $set))->assertNotFound();
});

test('the specialist can edit the values before publishing, and the page publishes', function () {
    $set = revPending(['weight' => '72.5']);
    $specialist = revUser(StaffRole::Specialist);

    $this->actingAs($specialist)
        ->put(route('specialist.reviews.update', $set), ['values' => ['weight' => '71', 'waist' => '', 'body_fat_percent' => '18.5']])
        ->assertRedirect(route('specialist.reviews.show', $set));

    $values = $set->fresh()?->measurements()->with('type')->get()
        ->mapWithKeys(fn ($m): array => [(string) $m->type?->key => (string) $m->value])->all();

    expect($values)->toBe(['weight' => '71.00', 'body_fat_percent' => '18.50']);

    $this->actingAs($specialist)
        ->post(route('specialist.reviews.publish', $set))
        ->assertRedirect(route('specialist.reviews.index'));

    expect($set->fresh()?->currentStatus())->toBe(MeasurementSetStatus::Published)
        ->and(AuditLog::query()->where('action', AuditAction::Publish->value)->where('subject_id', $set->getKey())->exists())->toBeTrue();
});

test('an out-of-range edit is refused and nothing changes', function () {
    $set = revPending(['weight' => '72.5']);

    $this->actingAs(revUser(StaffRole::Specialist))
        ->put(route('specialist.reviews.update', $set), ['values' => ['weight' => '900']])
        ->assertSessionHasErrors('values.weight');

    expect($set->fresh()?->measurements()->count())->toBe(1);
});

test('returning a set needs a comment; with one, the set stays pending and the comment is stored', function () {
    $set = revPending();
    $specialist = revUser(StaffRole::Specialist);

    $this->actingAs($specialist)
        ->post(route('specialist.reviews.return', $set), ['comment' => '   '])
        ->assertSessionHasErrors('comment');

    expect($set->fresh()?->note)->toBeNull();

    $this->actingAs($specialist)
        ->post(route('specialist.reviews.return', $set), ['comment' => 'دور کمر را دوباره بگیرید'])
        ->assertRedirect(route('specialist.reviews.index'));

    $fresh = $set->fresh();

    expect($fresh?->currentStatus())->toBe(MeasurementSetStatus::PendingReview)
        ->and($fresh?->note)->toBe('دور کمر را دوباره بگیرید');
});

test('a set cannot be published twice through the page', function () {
    $set = revPending();
    $specialist = revUser(StaffRole::Specialist);

    $this->actingAs($specialist)->post(route('specialist.reviews.publish', $set))->assertRedirect();
    $this->actingAs($specialist)->post(route('specialist.reviews.publish', $set))->assertSessionHasErrors('status');

    expect(AuditLog::query()->where('action', AuditAction::Publish->value)->where('subject_id', $set->getKey())->count())->toBe(1);
});
