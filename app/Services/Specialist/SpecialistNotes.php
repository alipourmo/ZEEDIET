<?php

declare(strict_types=1);

namespace App\Services\Specialist;

use App\Enums\AuditAction;
use App\Models\Client;
use App\Models\SpecialistNote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a specialist's note and lists the follow-ups that are due (spec sections 6.5 and 8.4). Needs note.manage.
 */
final class SpecialistNotes
{
    /** A follow-up is due when it falls within this many days, or is already overdue (spec 8.4). */
    public const FOLLOW_UP_WINDOW_DAYS = 7;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Adds a note. A null follow-up date clears the client's next follow-up, because the latest note decides it.
     * The audit row holds the follow-up date only, never the body.
     *
     * @throws AuthorizationException
     */
    public function add(Client $client, User $actor, string $body, ?CarbonImmutable $followUpOn): SpecialistNote
    {
        $this->authorize($actor);

        $note = SpecialistNote::query()->create([
            'client_id' => $client->getKey(),
            'specialist_id' => $actor->getKey(),
            'body' => $body,
            // A calendar date, written as Y-m-d so the column compares the same way on every database.
            'next_follow_up_on' => $followUpOn?->toDateString(),
        ]);

        $this->audit->log(AuditAction::Create, $note, (int) $client->getKey(), [
            'new' => ['follow_up_on' => $followUpOn?->toDateString()],
        ], $actor);

        return $note;
    }

    /**
     * The follow-ups that are due: for each client, the latest note, if its follow-up date is within the window or
     * overdue. Soft-deleted clients are left out. Oldest date first.
     *
     * @return Collection<int, SpecialistNote>
     *
     * @throws AuthorizationException
     */
    public function due(User $actor, CarbonImmutable $today): Collection
    {
        $this->authorize($actor);

        $latestPerClient = SpecialistNote::query()
            ->selectRaw('MAX(id)')
            ->groupBy('client_id');

        return SpecialistNote::query()
            ->whereIn('id', $latestPerClient)
            ->whereNotNull('next_follow_up_on')
            ->whereDate('next_follow_up_on', '<=', $today->addDays(self::FOLLOW_UP_WINDOW_DAYS)->toDateString())
            ->whereHas('client')
            ->with(['client', 'specialist'])
            ->orderBy('next_follow_up_on')
            ->orderBy('id')
            ->get();
    }

    private function authorize(User $actor): void
    {
        if (! Gate::forUser($actor)->allows('note.manage')) {
            throw new AuthorizationException;
        }
    }
}
