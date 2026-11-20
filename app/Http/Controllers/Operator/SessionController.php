<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\SessionRecordRequest;
use App\Models\Appointment;
use App\Models\DeviceSession;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Operator\TodayAppointments;
use App\Services\Sessions\SessionRecorder;
use App\Support\ClientAlertBadges;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The operator's record page for one of today's device appointments (spec sections 7.3 and 8.3). Every action is
 * behind session.record, and checks that the appointment is open today. Anything else is a 404, so a link to another
 * day's appointment cannot be used.
 */
class SessionController extends Controller
{
    public function __construct(private readonly SessionRecorder $recorder) {}

    public function create(Request $request, Appointment $appointment): View
    {
        abort_unless(TodayAppointments::canOpen($appointment), 404);

        $client = $appointment->client;
        abort_if($client === null, 404);

        $packages = $this->recorder->packagesFor((int) $appointment->client_id, (int) $appointment->service_id);

        $sessions = DeviceSession::query()
            ->where('appointment_id', $appointment->getKey())
            ->orderBy('session_number')
            ->get();

        return view('operator.record', [
            'title' => __('sessions.record_title', ['name' => $client->fullName()]),
            'appointment' => $appointment,
            'badges' => ClientAlertBadges::fromClient($client),
            'packages' => $packages,
            'nextPackageId' => $packages->first(fn (SessionPackage $package): bool => $package->remainingSessions() > 0)?->getKey(),
            'sessions' => $sessions,
            'canRecord' => in_array($appointment->currentStatus()->value, ['arrived', 'in_progress'], true),
        ]);
    }

    public function store(SessionRecordRequest $request, Appointment $appointment): RedirectResponse
    {
        abort_unless(TodayAppointments::canOpen($appointment), 404);

        $session = $this->recorder->record($appointment, $this->actor($request), $request->validated());

        $message = $session->package_id === null
            ? __('sessions.recorded_without_package', ['number' => Digits::toPersian($session->session_number)])
            : __('sessions.recorded_with_package', ['number' => Digits::toPersian($session->session_number)]);

        return redirect()->route('operator.sessions.create', $appointment)->with('success', $message);
    }

    public function finish(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless(TodayAppointments::canOpen($appointment), 404);

        $this->recorder->finish($appointment, $this->actor($request));

        return redirect()->route('operator.today')->with('success', __('sessions.finished'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
