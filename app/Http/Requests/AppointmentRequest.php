<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StaffRole;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Booking and rescheduling (spec sections 7.2 and 8.2). Admin and secretary: appointment.manage.
 *
 * The end time is not an input. It comes from the service's default length, so the form cannot make a booking of
 * the wrong length. The availability checks themselves are in BookingService.
 */
final class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('appointment.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'specialist_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', StaffRole::Specialist->value)->where('is_active', true)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('is_active', true)],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'string', 'date_format:Y-m-d H:i'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The data for BookingService::book() and reschedule(). Start times are Tehran wall-clock times.
     *
     * @return array{client_id: int, service_id: int, specialist_id: ?int, device_id: ?int, room_id: ?int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, note: ?string}
     */
    public function bookingData(): array
    {
        $serviceId = (int) $this->input('service_id');
        $service = Service::query()->findOrFail($serviceId);
        $start = CarbonImmutable::parse((string) $this->input('starts_at'), 'Asia/Tehran');

        return [
            'client_id' => (int) $this->input('client_id'),
            'service_id' => $serviceId,
            'specialist_id' => $this->nullableId('specialist_id'),
            'device_id' => $this->nullableId('device_id'),
            'room_id' => $this->nullableId('room_id'),
            'starts_at' => $start,
            'ends_at' => $start->addMinutes($service->default_duration_min),
            'note' => $this->filled('note') ? (string) $this->input('note') : null,
        ];
    }

    private function nullableId(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
