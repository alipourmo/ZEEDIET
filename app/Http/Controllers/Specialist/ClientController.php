<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\Specialist\ClientFile;
use App\Support\ClientAlertBadges;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The client file inside the specialist panel (spec section 8.4). Read-only apart from the medical record, which is
 * edited on its own page. Core client fields are edited from /admin.
 */
class ClientController extends Controller
{
    public function __construct(private readonly ClientFile $clientFile) {}

    public function show(Request $request, Client $client): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('clients.show', [
            'client' => $client,
            'extraView' => 'specialist.clients.file',
            'file' => $this->clientFile->open($client, $user),
            'canManageNotes' => Gate::allows('note.manage'),
            'summaryUrl' => route('specialist.clients.show', $client),
            'badges' => Gate::allows('medical.view_alerts') ? ClientAlertBadges::fromClient($client) : null,
            'editUrl' => null,
            'medicalUrl' => Gate::allows('medical.view') ? route('specialist.clients.medical.edit', $client) : null,
            'deleteUrl' => null,
            'backUrl' => null,
        ]);
    }
}
