<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use App\Models\User;
use App\Services\Clients\ClientService;
use App\Support\ClientAlertBadges;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Client list, file and CRUD (spec section 8.2). Each route has a can: middleware (see routes/web.php).
 */
class ClientController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly ClientService $clients) {}

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $query = Client::query()->orderBy('last_name')->orderBy('first_name')->orderBy('id');

        if ($term !== '') {
            // Persian digits and letters are already ASCII / Persian by NormalizeInput, so the mobile matches either way.
            $like = '%'.addcslashes($term, '%_\\').'%';

            $query->where(function ($q) use ($like): void {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            });
        }

        // Badges need only the flags, so the allergy text is reduced to yes/no in SQL (see ClientAlertBadges).
        $query->with(['medical' => fn ($q) => $q->select(ClientAlertBadges::medicalSelect())]);

        $page = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('clients.index', [
            'clients' => $page,
            'term' => $term,
            'alerts' => Gate::allows('medical.view_alerts')
                ? $page->getCollection()->mapWithKeys(fn (Client $client) => [$client->getKey() => ClientAlertBadges::fromClient($client)])
                : null,
        ]);
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = $this->clients->create($request->clientData(), $this->actor($request));

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', __('clients.created', ['code' => Digits::toPersian($client->code)]));
    }

    public function show(Client $client): View
    {
        return view('clients.show', [
            'client' => $client,
            'summaryUrl' => route('admin.clients.show', $client),
            'badges' => Gate::allows('medical.view_alerts') ? ClientAlertBadges::fromClient($client) : null,
            'editUrl' => Gate::allows('client.update') ? route('admin.clients.edit', $client) : null,
            'medicalUrl' => Gate::allows('medical.view') ? route('admin.clients.medical.edit', $client) : null,
            'deleteUrl' => Gate::allows('client.delete') ? route('admin.clients.destroy', $client) : null,
            'backUrl' => route('admin.clients.index'),
        ]);
    }

    public function edit(Client $client): View
    {
        return view('clients.edit', ['client' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clients->update($client, $request->clientData(), $this->actor($request));

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', __('clients.updated'));
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $this->clients->delete($client, $this->actor($request));

        return redirect()
            ->route('admin.clients.index')
            ->with('success', __('clients.deleted', ['code' => Digits::toPersian($client->code)]));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
