<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Enums\DeviceSessionStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientMedical;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\SessionPackage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Operator\TodayAppointments;
use App\Services\Scheduling\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a device session from an operator's appointment (spec section 7.3). Clinic time is Asia/Tehran.
 *
 * Rules, in order:
 * 1. The appointment starts today, needs a device, and is arrived or in progress.
 * 2. A contraindication between the client and the device refuses the session. Operators cannot override it (the MVP
 *    simplification in spec 7.3). The refusal is audited, and the audit row is written before the transaction so it
 *    survives the refusal.
 * 3. session_number is the highest number for this client and device, plus one. It is computed under the client's
 *    row lock. The unique key on the table is the last line of defence.
 * 4. The session links to the oldest package of the appointment's service that still has remaining sessions. If none
 *    has, package_id stays null.
 * 5. The first session moves an arrived appointment to in_progress. finish() moves it to completed.
 */
final class SessionRecorder
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BookingService $bookings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  intensity, area, duration_min and note, all optional
     *
     * @throws ValidationException when the appointment is not open for a session, or the device is contraindicated
     */
    public function record(Appointment $appointment, User $operator, array $data): DeviceSession
    {
        $device = $this->assertCanRecord($appointment);
        $clientId = (int) $appointment->client_id;

        $matched = $this->matchedContraindications($clientId, $device);

        if ($matched !== []) {
            // Outside the transaction below: the refusal must be kept.
            $this->audit->log(AuditAction::SessionRefused, $appointment, $clientId, [
                'device_id' => $device->getKey(),
                'keys' => $matched,
            ], $operator);

            throw ValidationException::withMessages([
                'contraindication' => __('sessions.refused_contraindication'),
            ]);
        }

        return DB::transaction(function () use ($appointment, $operator, $data, $device, $clientId): DeviceSession {
            // Lock order: client, then appointment (BookingService locks the appointment on a transition).
            Client::query()->whereKey($clientId)->lockForUpdate()->firstOrFail();

            $current = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $this->assertCanRecord($current);

            $highest = (int) DeviceSession::query()
                ->where('client_id', $clientId)
                ->where('device_id', $device->getKey())
                ->max('session_number');
            $number = $highest + 1;

            $package = $this->oldestPackageWithRemaining($clientId, (int) $current->service_id);

            /** @var DeviceSession $session */
            $session = DeviceSession::query()->create([
                'client_id' => $clientId,
                'appointment_id' => $current->getKey(),
                'package_id' => $package?->getKey(),
                'device_id' => $device->getKey(),
                'operator_id' => $operator->getKey(),
                'session_number' => $number,
                'status' => DeviceSessionStatus::Done,
                'parameters' => $this->parameters($data),
                'note' => $this->note($data),
                'performed_at' => CarbonImmutable::now('Asia/Tehran'),
            ]);

            if ($current->currentStatus() === AppointmentStatus::Arrived) {
                $this->bookings->transition($current, AppointmentStatus::InProgress, $operator);
            }

            $this->audit->log(AuditAction::Create, $session, $clientId, ['new' => [
                'session_number' => $number,
                'device_id' => $device->getKey(),
                'package_id' => $package?->getKey(),
                'status' => DeviceSessionStatus::Done->value,
            ]], $operator);

            return $session;
        });
    }

    /**
     * Moves the appointment to completed (spec 7.3, step 5). An arrived appointment goes through in_progress first, and
     * each move is written to the status history by BookingService.
     */
    public function finish(Appointment $appointment, User $operator): Appointment
    {
        $this->assertOpenToday($appointment);
        $clientId = (int) $appointment->client_id;

        return DB::transaction(function () use ($appointment, $operator, $clientId): Appointment {
            Client::query()->whereKey($clientId)->lockForUpdate()->firstOrFail();

            $current = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $from = $current->currentStatus();

            if (! in_array($from, [AppointmentStatus::Arrived, AppointmentStatus::InProgress], true)) {
                throw ValidationException::withMessages(['status' => __('sessions.not_ready')]);
            }

            if ($from === AppointmentStatus::Arrived) {
                $current = $this->bookings->transition($current, AppointmentStatus::InProgress, $operator);
            }

            return $this->bookings->transition($current, AppointmentStatus::Completed, $operator);
        });
    }

    /**
     * The client's packages for a service, oldest first, each with its done count loaded in the same query.
     *
     * @return Collection<int, SessionPackage>
     */
    public function packagesFor(int $clientId, int $serviceId): Collection
    {
        return SessionPackage::query()
            ->where('client_id', $clientId)
            ->where('service_id', $serviceId)
            ->withCount(['sessions as done_sessions' => fn (Builder $query) => $query
                ->where('status', DeviceSessionStatus::Done->value)])
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The device for an appointment that is open today and needs one. Throws when it is not.
     */
    private function assertOpenToday(Appointment $appointment): Device
    {
        if (! TodayAppointments::isToday($appointment)) {
            throw ValidationException::withMessages(['appointment' => __('sessions.not_today')]);
        }

        $appointment->loadMissing(['service', 'device']);

        if ($appointment->service?->requires_device !== true || $appointment->device === null) {
            throw ValidationException::withMessages(['appointment' => __('sessions.no_device')]);
        }

        return $appointment->device;
    }

    /**
     * Open today, and the client is arrived or in progress. Returns the device.
     */
    private function assertCanRecord(Appointment $appointment): Device
    {
        $device = $this->assertOpenToday($appointment);

        if (! in_array($appointment->currentStatus(), [AppointmentStatus::Arrived, AppointmentStatus::InProgress], true)) {
            throw ValidationException::withMessages(['status' => __('sessions.not_ready')]);
        }

        return $device;
    }

    /**
     * The contraindication keys the client and the device share. Empty when the session may go ahead.
     *
     * @return list<string>
     */
    private function matchedContraindications(int $clientId, Device $device): array
    {
        $medical = ClientMedical::query()
            ->select(['id', 'client_id', 'device_contraindications'])
            ->where('client_id', $clientId)
            ->first();

        $clientKeys = $medical === null ? [] : array_map('strval', (array) $medical->device_contraindications);
        $deviceKeys = array_map('strval', (array) $device->contraindications);

        return array_values(array_intersect($clientKeys, $deviceKeys));
    }

    private function oldestPackageWithRemaining(int $clientId, int $serviceId): ?SessionPackage
    {
        return $this->packagesFor($clientId, $serviceId)
            ->first(fn (SessionPackage $package): bool => $package->remainingSessions() > 0);
    }

    /**
     * Only the three named parameters are kept (spec 6.3). Empty values are dropped.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|int>|null
     */
    private function parameters(array $data): ?array
    {
        $values = [];

        foreach (['intensity', 'area', 'duration_min'] as $key) {
            $value = $data[$key] ?? null;

            if ($value !== null && $value !== '') {
                $values[$key] = $key === 'duration_min' ? (int) $value : (string) $value;
            }
        }

        return $values === [] ? null : $values;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function note(array $data): ?string
    {
        $note = trim((string) ($data['note'] ?? ''));

        return $note === '' ? null : $note;
    }
}
