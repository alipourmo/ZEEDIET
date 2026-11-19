<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Enums\StaffRole;
use App\Models\User;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Query for GET /admin/calendar (spec section 8.2). Read-only. Filters are optional; the date is Jalali.
 */
final class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('appointment.view_all');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', 'in:day,week,month'],
            'date' => ['nullable', 'string', 'max:20'],
            'specialist_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', StaffRole::Specialist->value)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('date') && Jalali::parseToGregorian((string) $this->input('date')) === null) {
                $validator->errors()->add('date', __('appointments.date_invalid'));
            }
        });
    }

    public function view(): string
    {
        $view = (string) $this->input('view', 'week');

        return in_array($view, ['day', 'week', 'month'], true) ? $view : 'week';
    }

    /**
     * The anchor date: the Jalali date given, or today, at Tehran midnight.
     */
    public function date(): CarbonImmutable
    {
        $raw = (string) $this->input('date', '');
        $gregorian = $raw === '' ? null : Jalali::parseToGregorian($raw);

        return CarbonImmutable::parse($gregorian ?? now('Asia/Tehran')->format('Y-m-d'), 'Asia/Tehran')->startOfDay();
    }

    /**
     * The filters that were set, as query values (ids as integers).
     *
     * @return array<string, int|string>
     */
    public function filters(): array
    {
        $filters = [];

        foreach (['specialist_id', 'device_id', 'room_id'] as $key) {
            $value = $this->input($key);

            if ($value !== null && $value !== '') {
                $filters[$key] = (int) $value;
            }
        }

        $status = (string) $this->input('status', '');

        if ($status !== '') {
            $filters['status'] = $status;
        }

        return $filters;
    }

    public function actor(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
