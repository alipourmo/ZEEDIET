<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Enums\PaymentMethod;
use App\Support\Digits;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A new payment (spec section 8.2). Admin and secretary: payment.record. The date is typed in Jalali, stored as the
 * chosen day at the current clinic time, and may not be in the future.
 */
final class StorePaymentRequest extends FormRequest
{
    /** Amount ceiling in Toman. A typing guard, not a business rule. */
    public const MAX_AMOUNT = 1000000000;

    public function authorize(): bool
    {
        return Gate::allows('payment.record');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->filled('amount')
                ? str_replace([',', '٬'], '', Digits::toAscii((string) $this->input('amount')))
                : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_AMOUNT],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'paid_at' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $date = is_string($value) ? Jalali::parseToGregorian($value) : null;

                if ($date === null) {
                    $fail(__('payments.errors.date_invalid'));

                    return;
                }

                if ($date > now('Asia/Tehran')->toDateString()) {
                    $fail(__('payments.errors.date_future'));
                }
            }],
            'package_id' => ['nullable', 'integer', Rule::exists('session_packages', 'id')],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => __('payments.errors.client_required'),
            'client_id.exists' => __('payments.errors.client_missing'),
            'amount.required' => __('payments.errors.amount_invalid'),
            'amount.integer' => __('payments.errors.amount_invalid'),
            'amount.min' => __('payments.errors.amount_invalid'),
            'amount.max' => __('payments.errors.amount_invalid'),
            'method.required' => __('payments.errors.method_invalid'),
            'method.enum' => __('payments.errors.method_invalid'),
            'paid_at.required' => __('payments.errors.date_required'),
            'package_id.exists' => __('payments.errors.package_invalid'),
            'reference.max' => __('payments.errors.reference_max'),
            'note.max' => __('payments.errors.note_max'),
        ];
    }

    /**
     * The data for PaymentService::record(). The date is Gregorian, with the current clinic time.
     *
     * @return array{client_id: int, appointment_id: null, package_id: ?int, amount: int, method: PaymentMethod, paid_at: CarbonImmutable, reference: ?string, note: ?string}
     */
    public function paymentData(): array
    {
        $date = Jalali::parseToGregorian((string) $this->input('paid_at')) ?? '';
        $time = now('Asia/Tehran')->format('H:i:s');

        $package = $this->input('package_id');
        $reference = $this->string('reference')->trim()->toString();
        $note = $this->string('note')->trim()->toString();

        return [
            'client_id' => (int) $this->input('client_id'),
            'appointment_id' => null,
            'package_id' => $package === null || $package === '' ? null : (int) $package,
            'amount' => (int) $this->input('amount'),
            'method' => PaymentMethod::from((string) $this->input('method')),
            'paid_at' => CarbonImmutable::parse($date.' '.$time, 'Asia/Tehran'),
            'reference' => $reference === '' ? null : $reference,
            'note' => $note === '' ? null : $note,
        ];
    }
}
