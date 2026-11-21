<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Support\Jalali;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The payments list filters (spec section 8.2): an optional client and an optional Jalali date range.
 */
final class PaymentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('payment.view');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $date = function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && Jalali::parseToGregorian($value) === null) {
                $fail(__('payments.errors.date_invalid'));
            }
        };

        return [
            'client' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'from' => ['nullable', 'string', $date],
            'to' => ['nullable', 'string', $date],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client.exists' => __('payments.errors.client_missing'),
        ];
    }

    /**
     * @return array{client_id: ?int, from: ?string, to: ?string}
     */
    public function filters(): array
    {
        $from = $this->filled('from') ? Jalali::parseToGregorian((string) $this->input('from')) : null;
        $to = $this->filled('to') ? Jalali::parseToGregorian((string) $this->input('to')) : null;

        return [
            'client_id' => $this->filled('client') ? (int) $this->input('client') : null,
            'from' => $from,
            'to' => $to,
        ];
    }
}
