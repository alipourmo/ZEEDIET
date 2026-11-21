<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\Payment;
use App\Models\SessionPackage;
use App\Services\Portal\ClientOwnedScope;
use App\Services\Progress\ProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The client's home page and the five tiles of spec 8.5. Every query is limited to the signed-in client.
 */
class PortalController extends Controller
{
    /** How many past appointments the list shows. */
    private const PAST_LIMIT = 50;

    public function __construct(private readonly ProgressService $progress) {}

    public function home(Request $request): View
    {
        $client = $this->client($request);
        $packages = $this->packages($client);

        return view('portal.home', [
            'client' => $client,
            'reportCount' => ClientOwnedScope::apply(MeasurementSet::query()->current(), $client)->count(),
            'upcomingCount' => $this->upcoming($client)->count(),
            'remaining' => $packages->sum(fn (SessionPackage $package): int => $package->remainingSessions()),
            'paymentCount' => ClientOwnedScope::apply(Payment::query(), $client)->count(),
        ]);
    }

    public function progress(Request $request): View
    {
        $client = $this->client($request);
        $packages = $this->packages($client);

        return view('portal.progress', [
            'client' => $client,
            'summary' => $this->progress->summary($client),
            'chart' => $this->progress->chart($client),
            'sessionsDone' => $packages->sum(fn (SessionPackage $package): int => $package->doneSessions()),
            'sessionsTotal' => $packages->sum(fn (SessionPackage $package): int => $package->totalSessions()),
        ]);
    }

    public function appointments(Request $request): View
    {
        $client = $this->client($request);

        return view('portal.appointments', [
            'upcoming' => $this->upcoming($client)->get(),
            'past' => ClientOwnedScope::apply(Appointment::query()->with('service'), $client)
                ->where('starts_at', '<', now('Asia/Tehran'))
                ->orderByDesc('starts_at')
                ->limit(self::PAST_LIMIT)
                ->get(),
        ]);
    }

    public function sessions(Request $request): View
    {
        return view('portal.sessions', [
            'packages' => $this->packages($this->client($request)),
        ]);
    }

    public function payments(Request $request): View
    {
        $client = $this->client($request);

        $payments = ClientOwnedScope::apply(Payment::query(), $client)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        // Reversals are negative rows, so the sum is the net amount paid (spec 8.2).
        return view('portal.payments', [
            'payments' => $payments,
            'net' => (int) $payments->sum(fn (Payment $payment): int => (int) $payment->getAttribute('amount')),
        ]);
    }

    /**
     * Upcoming appointments that are not cancelled, soonest first.
     *
     * @return Builder<Appointment>
     */
    private function upcoming(Client $client): Builder
    {
        return ClientOwnedScope::apply(Appointment::query()->with('service'), $client)
            ->where('starts_at', '>=', now('Asia/Tehran'))
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->orderBy('starts_at');
    }

    /**
     * @return Collection<int, SessionPackage>
     */
    private function packages(Client $client): Collection
    {
        return ClientOwnedScope::apply(SessionPackage::query()->with('service'), $client)
            ->orderByDesc('purchased_at')
            ->get();
    }

    /**
     * The signed-in client. The guard is checked before this runs, so a missing client is a broken session: 401.
     */
    protected function client(Request $request): Client
    {
        $client = $request->user('client');

        if (! $client instanceof Client) {
            throw new HttpException(401);
        }

        return $client;
    }
}
