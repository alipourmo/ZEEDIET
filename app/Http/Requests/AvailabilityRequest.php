<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StaffRole;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Query for GET /admin/api/availability (spec section 8.2). Read-only, but validated like every other request.
 * The date is Jalali, as the clinic enters it; the response gives both calendars.
 */
final class AvailabilityRequest extends FormRequest
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
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'specialist_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', StaffRole::Specialist->value)->where('is_active', true)],
            'device_id' => ['nullable', 'integer', Rule::exists('devices', 'id')->where('is_active', true)],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'from_jalali' => ['required', 'string', 'max:20'],
            'days' => ['nullable', 'integer', 'between:1,14'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->resourceIds() === []) {
                $validator->errors()->add('specialist_id', __('appointments.resource_required'));
            }

            if ($this->filled('from_jalali') && $this->fromDate() === null) {
                $validator->errors()->add('from_jalali', __('appointments.date_invalid'));
            }
        });
    }

    /**
     * The chosen resources, keyed by their appointment column. Empty fields are left out.
     *
     * @return array<string, int>
     */
    public function resourceIds(): array
    {
        $ids = [];

        foreach (['specialist_id', 'device_id', 'room_id'] as $key) {
            $value = $this->input($key);

            if ($value !== null && $value !== '') {
                $ids[$key] = (int) $value;
            }
        }

        return $ids;
    }

    /**
     * The first day of the range, at midnight in Tehran, or null when the Jalali date is not valid.
     */
    public function fromDate(): ?CarbonImmutable
    {
        $gregorian = Jalali::parseToGregorian((string) $this->input('from_jalali', ''));

        return $gregorian === null ? null : CarbonImmutable::parse($gregorian, 'Asia/Tehran')->startOfDay();
    }

    public function days(): int
    {
        return (int) $this->input('days', 7);
    }
}
