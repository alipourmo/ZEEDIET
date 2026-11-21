<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\SessionPackageRequest;
use App\Models\Client;
use App\Models\Service;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Sessions\PackageService;
use App\Support\Jalali;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Session packages for admin and secretary (spec sections 6.3 and 8.2). Every route needs package.manage.
 */
class PackageController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly PackageService $packages) {}

    public function index(Request $request): View
    {
        $clientId = $request->integer('client') ?: null;

        $query = SessionPackage::query()
            ->with(['client:id,first_name,last_name,code', 'service:id,name'])
            ->withCount(['sessions as done_sessions' => fn ($sessions) => $sessions->done()])
            ->latest('purchased_at')
            ->latest('id');

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        return view('admin.packages.index', [
            'packages' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'client' => $clientId === null ? null : Client::query()->find($clientId),
            'newUrl' => route('admin.packages.create', $clientId === null ? [] : ['client' => $clientId]),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.packages.form', [
            'clients' => Client::query()->orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'code']),
            'services' => Service::query()
                ->where('is_active', true)
                ->where('kind', ServiceKind::DeviceSession->value)
                ->orderBy('name')
                ->get(['id', 'name', 'default_price']),
            'prefillClient' => $request->integer('client') ?: null,
            'today' => Jalali::formatGregorianAsJalali(now('Asia/Tehran')->toDateString()),
        ]);
    }

    public function store(SessionPackageRequest $request): RedirectResponse
    {
        $package = $this->packages->create($request->packageData(), $this->actor($request));

        return redirect()
            ->route('admin.packages.index', ['client' => $package->getAttribute('client_id')])
            ->with('success', __('packages.saved'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
