<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The reason for a reversing entry (spec sections 6.5 and 8.2). Admin only: payment.reverse. The reason is required.
 */
final class ReversePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('payment.reverse');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('payments.errors.note_required'),
            'note.max' => __('payments.errors.note_max'),
        ];
    }

    public function reason(): string
    {
        return $this->string('note')->trim()->toString();
    }
}
