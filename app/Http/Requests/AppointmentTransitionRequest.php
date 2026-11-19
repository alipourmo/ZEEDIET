<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A status change from the appointment screen (spec sections 7.2 and 8.2). Admin and secretary: appointment.manage.
 *
 * Only the moves the staff screen offers are accepted here. in_progress and completed come with the S3 operator
 * screens. The state machine itself, and the rule that a cancel needs a reason, are in BookingService::transition().
 */
final class AppointmentTransitionRequest extends FormRequest
{
    /**
     * The target statuses the staff form offers. Order is the order of the buttons.
     *
     * @var list<string>
     */
    public const OFFERED = ['confirmed', 'arrived', 'no_show', 'cancelled'];

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
            'to' => ['required', 'string', 'in:'.implode(',', self::OFFERED)],
            // Optional here. BookingService refuses a cancel without a reason, so the rule is in one place.
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => __('appointments.action_not_offered'),
            'to.in' => __('appointments.action_not_offered'),
        ];
    }

    public function targetStatus(): AppointmentStatus
    {
        return AppointmentStatus::from((string) $this->input('to'));
    }

    public function cancelReason(): ?string
    {
        $reason = $this->input('cancel_reason');

        return is_string($reason) ? $reason : null;
    }
}
