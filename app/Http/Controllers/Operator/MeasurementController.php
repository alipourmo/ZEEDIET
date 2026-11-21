<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\MeasurementRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Operator\TodayAppointments;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The operator's measurement form on the record page (spec 8.3). The set is saved as pending_review.
 */
class MeasurementController extends Controller
{
    public function __construct(private readonly MeasurementRecorder $recorder) {}

    public function store(MeasurementRequest $request, Appointment $appointment): RedirectResponse
    {
        abort_unless(TodayAppointments::canOpen($appointment), 404);

        if (! in_array($appointment->currentStatus(), [AppointmentStatus::Arrived, AppointmentStatus::InProgress], true)) {
            throw ValidationException::withMessages(['status' => __('sessions.not_ready')]);
        }

        $client = $appointment->client;
        abort_if($client === null, 404);

        $validated = $request->validated();
        $values = Arr::only(is_array($validated['values'] ?? null) ? $validated['values'] : [], MeasurementRecorder::OPERATOR_KEYS);
        $note = isset($validated['note']) ? (string) $validated['note'] : null;

        $this->recorder->record($client, $this->actor($request), CarbonImmutable::now('Asia/Tehran'), $values, $note);

        return redirect()->route('operator.sessions.create', $appointment)->with('success', __('measurements.recorded'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
