<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Device;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use App\Services\Scheduling\BookingService;
use App\Support\Jalali;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Appointment form: book, and reschedule (spec sections 7.2 and 8.2). Admin and secretary: appointment.manage on each
 * route. Every write goes through BookingService, which runs the availability checks under row locks.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function create(Request $request): View
    {
        return view('admin.appointments.form', [
            'title' => __('appointments.title_new'),
            'action' => route('admin.appointments.store'),
            'method' => 'POST',
            'appointment' => null,
            'editable' => true,
            'prefillClient' => $request->integer('client') ?: null,
            'pickerDate' => Jalali::formatGregorianAsJalali(now()->format('Y-m-d')),
            ...$this->options(null),
        ]);
    }

    public function store(AppointmentRequest $request): RedirectResponse
    {
        $appointment = $this->bookings->book($request->bookingData(), $this->actor($request));

        return redirect()->route('admin.appointments.edit', $appointment)->with('success', __('appointments.saved'));
    }

    public function edit(Appointment $appointment): View
    {
        $status = $appointment->currentStatus();

        return view('admin.appointments.form', [
            'title' => __('appointments.title_edit'),
            'action' => route('admin.appointments.update', $appointment),
            'method' => 'PUT',
            'appointment' => $appointment,
            'editable' => in_array($status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true),
            'prefillClient' => null,
            'pickerDate' => Jalali::formatGregorianAsJalali($appointment->time('starts_at')->format('Y-m-d')),
            ...$this->options($appointment),
        ]);
    }

    public function update(AppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $this->bookings->reschedule($appointment, $request->bookingData(), $this->actor($request));

        return redirect()->route('admin.appointments.edit', $appointment)->with('success', __('appointments.rescheduled'));
    }

    /**
     * The choices for the form. Inactive items are left out, except the one the appointment already uses, so an
     * existing booking still shows what it holds.
     *
     * @return array{clients: mixed, services: mixed, specialists: mixed, devices: mixed, rooms: mixed}
     */
    private function options(?Appointment $current): array
    {
        return [
            'clients' => Client::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'code']),
            'services' => $this->active(Service::query()->orderBy('name'), $current?->service_id)->get(),
            'specialists' => $this->active(
                User::query()->where('role', StaffRole::Specialist->value)->orderBy('name'),
                $current?->specialist_id,
            )->get(['id', 'name']),
            'devices' => $this->active(Device::query()->orderBy('name'), $current?->device_id)->get(['id', 'name']),
            'rooms' => $this->active(Room::query()->orderBy('name'), $current?->room_id)->get(['id', 'name']),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function active(Builder $query, ?int $currentId): Builder
    {
        return $query->where(function (Builder $inner) use ($currentId): void {
            $inner->where('is_active', true);

            if ($currentId !== null) {
                $inner->orWhere('id', $currentId);
            }
        });
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
