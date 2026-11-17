<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Support\ClientAlertBadges;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The client file inside the specialist panel (spec section 8.4). Read-only apart from the medical record, which is
 * edited on its own page. Core client fields are edited from /admin.
 */
class ClientController extends Controller
{
    public function show(Client $client): View
    {
        return view('clients.show', [
            'client' => $client,
            'summaryUrl' => route('specialist.clients.show', $client),
            'badges' => Gate::allows('medical.view_alerts') ? ClientAlertBadges::fromClient($client) : null,
            'editUrl' => null,
            'medicalUrl' => Gate::allows('medical.view') ? route('specialist.clients.medical.edit', $client) : null,
            'deleteUrl' => null,
            'backUrl' => null,
        ]);
    }
}
