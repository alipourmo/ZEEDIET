<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Enums\StaffRole;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\SpecialistNote;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Reports\ReportWorkflow;
use App\Services\Specialist\SpecialistNotes;
use App\Support\Display;
use Carbon\CarbonImmutable;
use Database\Seeders\MeasurementTypeSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(MeasurementTypeSeeder::class);
});

function cfUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

/** The clinic's day, offset by a number of days. */
function cfDay(int $days): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Tehran')->startOfDay()->addDays($days);
}

function cfNote(Client $client, string $body, ?CarbonImmutable $followUp = null, ?User $by = null): SpecialistNote
{
    return app(SpecialistNotes::class)->add($client, $by ?? cfUser(StaffRole::Specialist), $body, $followUp);
}

/**
 * @param  array<string, string>  $values
 */
function cfPublish(array $values, Client $client, CarbonImmutable $at): MeasurementSet
{
    $set = app(MeasurementRecorder::class)->record($client, cfUser(StaffRole::Operator), $at, $values);

    return app(ReportWorkflow::class)->publish($set, cfUser(StaffRole::Specialist));
}

/**
 * @param  array<string, string>  $values
 */
function cfPending(array $values, Client $client, CarbonImmutable $at): MeasurementSet
{
    return app(MeasurementRecorder::class)->record($client, cfUser(StaffRole::Operator), $at, $values);
}

test('a specialist adds a note with a Jalali follow-up date, stored as a Gregorian date', function () {
    $client = makeClient();
    $specialist = cfUser(StaffRole::Specialist);
    $target = cfDay(10);

    $this->actingAs($specialist)
        ->post(route('specialist.notes.store', $client), [
            'body' => 'جلسهٔ اول انجام شد',
            'next_follow_up_on' => Display::jdate($target),
        ])
        ->assertRedirect(route('specialist.clients.show', $client).'#notes');

    $note = SpecialistNote::query()->where('client_id', $client->getKey())->firstOrFail();

    expect($note->body)->toBe('جلسهٔ اول انجام شد')
        ->and($note->specialist_id)->toBe($specialist->getKey())
        ->and($note->next_follow_up_on?->toDateString())->toBe($target->toDateString());
});

test('the audit row holds the follow-up date and never the note text', function () {
    $client = makeClient();
    $target = cfDay(5);

    cfNote($client, 'متن محرمانه پزشکی', $target);

    $log = AuditLog::query()
        ->where('action', AuditAction::Create->value)
        ->where('subject_type', SpecialistNote::class)
        ->firstOrFail();

    expect(json_encode($log->changes, JSON_UNESCAPED_UNICODE))->not->toContain('متن محرمانه')
        ->and($log->changes['new']['follow_up_on'] ?? null)->toBe($target->toDateString())
        ->and($log->client_id)->toBe($client->getKey());
});

test('a note needs text, and a follow-up date must be a real Jalali date', function () {
    $client = makeClient();
    $specialist = cfUser(StaffRole::Specialist);

    $this->actingAs($specialist)
        ->post(route('specialist.notes.store', $client), ['body' => '   '])
        ->assertSessionHasErrors('body');

    $this->actingAs($specialist)
        ->post(route('specialist.notes.store', $client), ['body' => 'یادداشت', 'next_follow_up_on' => '۱۴۰۵/۱۳/۹۹'])
        ->assertSessionHasErrors('next_follow_up_on');

    expect(SpecialistNote::query()->count())->toBe(0);
});

test('an empty follow-up date is allowed and clears the next follow-up', function () {
    $client = makeClient();
    cfNote($client, 'اولین یادداشت', cfDay(4));

    $this->actingAs(cfUser(StaffRole::Specialist))
        ->post(route('specialist.notes.store', $client), ['body' => 'یادداشت بدون پیگیری', 'next_follow_up_on' => ''])
        ->assertRedirect();

    $this->actingAs(cfUser(StaffRole::Specialist))
        ->get(route('specialist.clients.show', $client))
        ->assertOk()
        ->assertSee(__('followups.none_set'));
});

test('secretary, operator and admin cannot add a note, and the service refuses too', function (StaffRole $role) {
    $client = makeClient();
    $user = cfUser($role);

    $this->actingAs($user)
        ->post(route('specialist.notes.store', $client), ['body' => 'نفوذ'])
        ->assertForbidden();

    expect(fn () => app(SpecialistNotes::class)->add($client, $user, 'نفوذ', null))
        ->toThrow(AuthorizationException::class)
        ->and(SpecialistNote::query()->count())->toBe(0);
})->with([
    'admin' => StaffRole::Admin,
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
]);

test('notes are append-only: they cannot be changed or deleted', function () {
    $note = cfNote(makeClient(), 'یادداشت ثابت');

    expect(fn () => $note->update(['body' => 'تغییر']))->toThrow(LogicException::class)
        ->and(fn () => $note->delete())->toThrow(LogicException::class)
        ->and($note->fresh()?->body)->toBe('یادداشت ثابت');
});

test('the client file shows the notes and the published history, and never the pending values', function () {
    $client = makeClient();
    cfPublish(['weight' => '70.5'], $client, cfDay(-30));
    cfPending(['weight' => '41.25'], $client, cfDay(-1));
    cfNote($client, 'یادداشت تست برای پرونده');

    $this->actingAs(cfUser(StaffRole::Specialist))
        ->get(route('specialist.clients.show', $client))
        ->assertOk()
        ->assertSee('یادداشت تست برای پرونده')
        ->assertSee('۷۰.۵۰')
        ->assertDontSee('۴۱.۲۵')
        ->assertSee('data-chart-line', false)
        ->assertSee(__('progress.history_title'));
});

test('the client file lists only this client\'s notes', function () {
    $client = makeClient();
    $other = makeClient();
    cfNote($other, 'یادداشت یک مراجعهٔ دیگر');

    $this->actingAs(cfUser(StaffRole::Specialist))
        ->get(route('specialist.clients.show', $client))
        ->assertOk()
        ->assertDontSee('یادداشت یک مراجعهٔ دیگر')
        ->assertSee(__('notes.empty'));
});

test('the file shows the latest note\'s follow-up, not an earlier one', function () {
    $client = makeClient();
    cfNote($client, 'اول', cfDay(3));
    cfNote($client, 'دوم', cfDay(9));

    $this->actingAs(cfUser(StaffRole::Specialist))
        ->get(route('specialist.clients.show', $client))
        ->assertOk()
        ->assertSee(__('followups.next_on', ['date' => Display::jdate(cfDay(9))]))
        ->assertDontSee(__('followups.next_on', ['date' => Display::jdate(cfDay(3))]));
});

test('opening the client file is audited as a view of the client, without the note text', function () {
    $client = makeClient();
    cfNote($client, 'متن برای بررسی ممیزی');

    $this->actingAs(cfUser(StaffRole::Specialist))->get(route('specialist.clients.show', $client))->assertOk();

    $log = AuditLog::query()
        ->where('action', AuditAction::View->value)
        ->where('subject_type', Client::class)
        ->where('subject_id', $client->getKey())
        ->firstOrFail();

    expect($log->changes['sections'] ?? null)->toBe(['notes', 'measurements'])
        ->and(json_encode($log->changes, JSON_UNESCAPED_UNICODE))->not->toContain('متن برای بررسی ممیزی');
});

test('a specialist file page is not shown to the secretary, operator or admin', function (StaffRole $role) {
    $client = makeClient();

    $this->actingAs(cfUser($role))
        ->get(route('specialist.clients.show', $client))
        ->assertForbidden();
})->with([
    'admin' => StaffRole::Admin,
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
]);
