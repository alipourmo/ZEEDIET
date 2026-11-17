<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClientMedicalRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\Clients\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The medical record page. Opening it is audited (spec section 7.8). Route middleware: can:medical.view and
 * can:medical.update.
 */
class ClientMedicalController extends Controller
{
    public function __construct(private readonly ClientService $clients) {}

    public function edit(Request $request, Client $client): View
    {
        $medical = $this->clients->viewMedical($client, $this->actor($request));

        return view('clients.medical', [
            'client' => $client,
            'medical' => $medical,
            'formAction' => route('admin.clients.medical.update', $client),
            'backUrl' => route('admin.clients.show', $client),
        ]);
    }

    public function update(UpdateClientMedicalRequest $request, Client $client): RedirectResponse
    {
        $this->clients->updateMedical($client, $request->medicalData(), $this->actor($request));

        return redirect()
            ->route('admin.clients.medical.edit', $client)
            ->with('success', __('clients.medical_saved'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
