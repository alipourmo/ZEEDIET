<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\AuditAction;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\Client;
use App\Models\Device;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes appointments (spec sections 7.1 and 7.2). Every change runs in one transaction that locks first.
 *
 * Locking. A booking locks, in this fixed order, the client, the device, the room and the specialist. A
 * reschedule locks the appointment first, then the same rows in the same order. A status change locks only the
 * appointment. One order everywhere means no two transactions wait on each other in a cycle.
 *
 * Why the locks come before any read. Under MySQL REPEATABLE READ the snapshot is taken at the first ordinary read.
 * If a read came first, the availability check could miss a booking that committed a moment earlier. So each
 * transaction locks first, then reads.
 *
 * Why the resource row, not the overlapping appointments. Locking the resource row serialises every booking of
 * that resource, including a booking into a gap where no row exists yet. Locking only existing overlapping rows
 * misses that case.
 */
final class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Books a new appointment. Throws ValidationException, keyed by the form field, when it cannot be made.
     *
     * @param  array{client_id: int, service_id: int, specialist_id?: ?int, device_id?: ?int, room_id?: ?int, starts_at: CarbonInterface, ends_at: CarbonInterface, note?: ?string}  $data
     */
    public function book(array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($data, $actor): Appointment {
            $resources = $this->lock(
                (int) $data['client_id'],
                $this->optionalId($data, 'device_id'),
                $this->optionalId($data, 'room_id'),
                $this->optionalId($data, 'specialist_id'),
            );

            $service = $this->serviceFor($data);
            [$start, $end] = $this->interval($data);

            $this->assertUsable($service, $resources, $data);
            $this->assertFree($start, $end, $data, null);

            $appointment = Appointment::query()->create([
                'client_id' => (int) $data['client_id'],
                'service_id' => (int) $data['service_id'],
                'specialist_id' => $this->optionalId($data, 'specialist_id'),
                'device_id' => $this->optionalId($data, 'device_id'),
                'room_id' => $this->optionalId($data, 'room_id'),
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => AppointmentStatus::Scheduled,
                'source' => AppointmentSource::Staff,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            $this->recordHistory($appointment, null, AppointmentStatus::Scheduled, $actor);

            $this->audit->log(AuditAction::Create, $appointment, $appointment->client_id, ['new' => $this->summary($appointment)], $actor);

            return $appointment;
        });
    }

    /**
     * Moves a scheduled or confirmed appointment to a new time or new resources. This is not a status change, so it
     * writes no history row. It does re-run every availability check, ignoring the appointment itself, and it writes
     * an audit row with the old and new times (spec 7.2).
     *
     * @param  array{service_id?: int, specialist_id?: ?int, device_id?: ?int, room_id?: ?int, starts_at: CarbonInterface, ends_at: CarbonInterface}  $data
     */
    public function reschedule(Appointment $appointment, array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $actor): Appointment {
            $current = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            $resources = $this->lock(
                (int) $current->client_id,
                $this->optionalId($data, 'device_id'),
                $this->optionalId($data, 'room_id'),
                $this->optionalId($data, 'specialist_id'),
            );

            $status = $current->status;

            if (! in_array($status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
                throw ValidationException::withMessages(['status' => __('scheduling.reschedule_not_allowed')]);
            }

            $service = Service::query()->whereKey($current->service_id)->firstOrFail();
            [$start, $end] = $this->interval($data);

            $this->assertUsable($service, $resources, $data);
            $this->assertFree($start, $end, $data + ['client_id' => $current->client_id], $current->getKey());

            $old = $this->summary($current);

            $current->fill([
                'specialist_id' => $this->optionalId($data, 'specialist_id'),
                'device_id' => $this->optionalId($data, 'device_id'),
                'room_id' => $this->optionalId($data, 'room_id'),
                'starts_at' => $start,
                'ends_at' => $end,
            ]);
            $current->save();

            $this->audit->log(AuditAction::Update, $current, $current->client_id, [
                'old' => $old,
                'new' => $this->summary($current),
            ], $actor);

            return $current;
        });
    }

    /**
     * Moves an appointment along the allowed path of spec section 7.2. Anything else is refused. Every move writes a
     * history row and an audit row. Cancelling needs a reason.
     */
    public function transition(Appointment $appointment, AppointmentStatus $to, User $actor, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $to, $actor, $reason): Appointment {
            $current = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            $from = $current->status;

            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages(['status' => __('scheduling.illegal_transition', [
                    'from' => __('scheduling.status.'.$from->value),
                    'to' => __('scheduling.status.'.$to->value),
                ])]);
            }

            $reason = $reason === null ? '' : trim($reason);

            if ($to === AppointmentStatus::Cancelled && $reason === '') {
                throw ValidationException::withMessages(['cancel_reason' => __('scheduling.cancel_reason_required')]);
            }

            $old = ['status' => $from->value];

            $current->status = $to;

            if ($to === AppointmentStatus::Cancelled) {
                $current->cancel_reason = $reason;
            }

            $current->save();

            $this->recordHistory($current, $from, $to, $actor);

            $changes = ['old' => $old, 'new' => ['status' => $to->value]];

            if ($to === AppointmentStatus::Cancelled) {
                $changes['new']['cancel_reason'] = $reason;
            }

            $this->audit->log(AuditAction::Update, $current, $current->client_id, $changes, $actor);

            return $current;
        });
    }

    /**
     * Cancels an appointment with its reason (spec 7.2).
     */
    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::Cancelled, $actor, $reason);
    }

    /**
     * Takes the row locks in the fixed order, and returns the models found. A missing or inactive resource is
     * reported later, by assertUsable(), so every failure reads the same way.
     *
     * @return array{client: ?Client, device: ?Device, room: ?Room, specialist: ?User}
     */
    private function lock(int $clientId, ?int $deviceId, ?int $roomId, ?int $specialistId): array
    {
        $client = Client::query()->whereKey($clientId)->lockForUpdate()->first();

        $device = $deviceId === null ? null : Device::query()->whereKey($deviceId)->lockForUpdate()->first();
        $room = $roomId === null ? null : Room::query()->whereKey($roomId)->lockForUpdate()->first();
        $specialist = $specialistId === null ? null : User::query()->whereKey($specialistId)->lockForUpdate()->first();

        return ['client' => $client, 'device' => $device, 'room' => $room, 'specialist' => $specialist];
    }

    /**
     * @param  array{client: ?Client, device: ?Device, room: ?Room, specialist: ?User}  $resources
     * @param  array<string, mixed>  $data
     */
    private function assertUsable(Service $service, array $resources, array $data): void
    {
        $errors = [];

        if ($resources['client'] === null) {
            $errors['client_id'] = __('scheduling.client_missing');
        }

        if (! $service->is_active) {
            $errors['service_id'] = __('scheduling.service_inactive');
        }

        if ($service->requires_device && $this->optionalId($data, 'device_id') === null) {
            $errors['device_id'] = __('scheduling.device_required');
        } elseif ($this->optionalId($data, 'device_id') !== null && ($resources['device'] === null || ! $resources['device']->is_active)) {
            $errors['device_id'] = __('scheduling.device_inactive');
        }

        if ($service->requires_specialist && $this->optionalId($data, 'specialist_id') === null) {
            $errors['specialist_id'] = __('scheduling.specialist_required');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertFree(CarbonInterface $start, CarbonInterface $end, array $data, ?int $ignoreAppointmentId): void
    {
        $problems = $this->availability->problems(
            $start,
            $end,
            (int) $data['client_id'],
            $this->optionalId($data, 'specialist_id'),
            $this->optionalId($data, 'device_id'),
            $this->optionalId($data, 'room_id'),
            $ignoreAppointmentId,
        );

        if ($problems !== []) {
            throw ValidationException::withMessages($problems);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function serviceFor(array $data): Service
    {
        return Service::query()->whereKey((int) $data['service_id'])->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end] in clinic time
     */
    private function interval(array $data): array
    {
        return [
            CarbonImmutable::instance($data['starts_at'])->setTimezone('Asia/Tehran'),
            CarbonImmutable::instance($data['ends_at'])->setTimezone('Asia/Tehran'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalId(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    private function recordHistory(Appointment $appointment, ?AppointmentStatus $from, AppointmentStatus $to, User $actor): void
    {
        AppointmentStatusHistory::query()->create([
            'appointment_id' => $appointment->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $actor->getKey(),
        ]);
    }

    /**
     * The fields worth keeping in the audit trail, as plain values.
     *
     * @return array<string, mixed>
     */
    private function summary(Appointment $appointment): array
    {
        return [
            'client_id' => $appointment->client_id,
            'service_id' => $appointment->service_id,
            'specialist_id' => $appointment->specialist_id,
            'device_id' => $appointment->device_id,
            'room_id' => $appointment->room_id,
            'starts_at' => $appointment->starts_at?->format('Y-m-d H:i'),
            'ends_at' => $appointment->ends_at?->format('Y-m-d H:i'),
            'status' => $appointment->status->value,
        ];
    }
}
