<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\AppointmentStatus;
use App\Enums\MeasurementSetStatus;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\MeasurementSet;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Counts for the admin dashboard and the daily report (spec sections 8.2 and 12, S4-T6). The definitions are the
 * spec's: daily clients = distinct clients with a completed appointment that day; new clients = created in the range;
 * no-show rate = no_show / (completed + no_show). Cancelled appointments are not counted as appointments.
 */
final class DashboardStats
{
    /** Appointments on the day, cancelled ones left out. */
    public function appointmentsOn(CarbonImmutable $day): int
    {
        return Appointment::query()
            ->whereBetween('starts_at', $this->bounds($day))
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->count();
    }

    /** Clients who have arrived and are waiting to be seen. */
    public function arrivalsWaiting(CarbonImmutable $day): int
    {
        return Appointment::query()
            ->whereBetween('starts_at', $this->bounds($day))
            ->where('status', AppointmentStatus::Arrived->value)
            ->count();
    }

    /** Clients created from the Saturday that starts the Persian week, through the end of the day. */
    public function newClientsThisWeek(CarbonImmutable $day): int
    {
        $start = $day->startOfWeek(CarbonInterface::SATURDAY)->startOfDay();

        return Client::query()->whereBetween('created_at', [$start, $day->endOfDay()])->count();
    }

    public function newClientsOn(CarbonImmutable $day): int
    {
        return Client::query()->whereBetween('created_at', $this->bounds($day))->count();
    }

    /** Distinct clients with a completed appointment on the day. */
    public function dailyClients(CarbonImmutable $day): int
    {
        return Appointment::query()
            ->whereBetween('starts_at', $this->bounds($day))
            ->where('status', AppointmentStatus::Completed->value)
            ->distinct()
            ->count('client_id');
    }

    /**
     * Every appointment status with its count on the day, zero when none. The cancelled row is included here, so the
     * report shows where every appointment went.
     *
     * @return array<string, int>
     */
    public function appointmentsByStatus(CarbonImmutable $day): array
    {
        $counts = Appointment::query()
            ->whereBetween('starts_at', $this->bounds($day))
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];

        foreach (AppointmentStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * no_show / (completed + no_show) for the day, or null when neither happened.
     */
    public function noShowRate(CarbonImmutable $day): ?float
    {
        $byStatus = $this->appointmentsByStatus($day);
        $completed = $byStatus[AppointmentStatus::Completed->value];
        $noShow = $byStatus[AppointmentStatus::NoShow->value];
        $denominator = $completed + $noShow;

        return $denominator === 0 ? null : $noShow / $denominator;
    }

    /**
     * The net amount per method on the day: payments minus reversals, in Toman.
     *
     * @return array{total: int, byMethod: array<string, int>}
     */
    public function paymentsOn(CarbonImmutable $day): array
    {
        $sums = Payment::query()
            ->whereBetween('paid_at', $this->bounds($day))
            ->selectRaw('method, SUM(amount) AS total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $byMethod = [];

        foreach (PaymentMethod::cases() as $method) {
            $byMethod[$method->value] = (int) ($sums[$method->value] ?? 0);
        }

        return ['total' => array_sum($byMethod), 'byMethod' => $byMethod];
    }

    /** Measurement sets waiting for a specialist's review. */
    public function pendingReports(): int
    {
        return MeasurementSet::query()
            ->where('status', MeasurementSetStatus::PendingReview->value)
            ->count();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bounds(CarbonImmutable $day): array
    {
        return [$day->startOfDay(), $day->endOfDay()];
    }
}
