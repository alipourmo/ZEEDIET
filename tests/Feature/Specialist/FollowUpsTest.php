<?php

declare(strict_types=1);

use App\Enums\StaffRole;
use App\Models\Client;
use App\Models\SpecialistNote;
use App\Models\User;
use App\Services\Specialist\SpecialistNotes;
use App\Support\Display;
use Carbon\CarbonImmutable;

function fuUser(StaffRole $role): User
{
    return User::factory()->role($role)->create(['is_active' => true]);
}

function fuToday(): CarbonImmutable
{
    return CarbonImmutable::now('Asia/Tehran')->startOfDay();
}

function fuNote(Client $client, ?CarbonImmutable $followUp): SpecialistNote
{
    return app(SpecialistNotes::class)->add($client, fuUser(StaffRole::Specialist), 'پیگیری', $followUp);
}

/**
 * The client ids that are due, in the order the list shows them.
 *
 * @return list<int>
 */
function fuDueIds(): array
{
    return app(SpecialistNotes::class)
        ->due(fuUser(StaffRole::Specialist), fuToday())
        ->map(fn (SpecialistNote $note): int => (int) $note->client_id)
        ->values()
        ->all();
}

test('a follow-up within seven days, or overdue, is due; one further away is not', function () {
    $within = makeClient();
    $boundary = makeClient();
    $overdue = makeClient();
    $far = makeClient();

    fuNote($within, fuToday()->addDays(3));
    fuNote($boundary, fuToday()->addDays(7));
    fuNote($overdue, fuToday()->subDays(2));
    fuNote($far, fuToday()->addDays(8));

    expect(fuDueIds())->toBe([$overdue->getKey(), $within->getKey(), $boundary->getKey()]);
});

test('only the latest note decides a client\'s follow-up', function () {
    $cleared = makeClient();
    $rescheduled = makeClient();

    fuNote($cleared, fuToday()->addDays(2));
    fuNote($cleared, null);

    fuNote($rescheduled, fuToday()->subDays(1));
    fuNote($rescheduled, fuToday()->addDays(5));

    $ids = fuDueIds();

    expect($ids)->toBe([$rescheduled->getKey()])
        ->and($ids)->not->toContain($cleared->getKey());
});

test('a soft-deleted client is left out of the follow-ups', function () {
    $client = makeClient();
    fuNote($client, fuToday()->addDays(1));

    $client->delete();

    expect(fuDueIds())->toBe([]);
});

test('the follow-ups page lists the due clients, marks the overdue ones, and links to the file', function () {
    $overdue = makeClient();
    $soon = makeClient();
    fuNote($overdue, fuToday()->subDays(4));
    fuNote($soon, fuToday()->addDays(2));

    $this->actingAs(fuUser(StaffRole::Specialist))
        ->get(route('specialist.followups'))
        ->assertOk()
        ->assertSee(__('followups.overdue'))
        ->assertSee(Display::jdate(fuToday()->subDays(4)))
        ->assertSee(route('specialist.clients.show', $overdue).'#notes');
});

test('an empty list says so', function () {
    $this->actingAs(fuUser(StaffRole::Specialist))
        ->get(route('specialist.followups'))
        ->assertOk()
        ->assertSee(__('followups.none'));
});

test('secretary, operator and admin cannot reach the follow-ups page', function (StaffRole $role) {
    $this->actingAs(fuUser($role))
        ->get(route('specialist.followups'))
        ->assertForbidden();
})->with([
    'admin' => StaffRole::Admin,
    'secretary' => StaffRole::Secretary,
    'operator' => StaffRole::Operator,
]);
