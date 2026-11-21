<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\StoreSpecialistNoteRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\Specialist\SpecialistNotes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Adds a note to the client file (spec 8.4). The route checks note.manage.
 */
class SpecialistNoteController extends Controller
{
    public function __construct(private readonly SpecialistNotes $notes) {}

    public function store(StoreSpecialistNoteRequest $request, Client $client): RedirectResponse
    {
        $this->notes->add(
            $client,
            $this->actor($request),
            (string) $request->validated('body'),
            $request->followUpOn(),
        );

        return redirect()
            ->route('specialist.clients.show', $client)
            ->withFragment('notes')
            ->with('success', __('notes.saved'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
