<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ServiceKind;
use App\Support\Digits;
use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A new session package (spec sections 6.3 and 8.2). Admin and secretary: package.manage.
 *
 * Only device-session services can be sold as packages. The purchase date is typed in Jalali and stored as Gregorian.
 */
final class SessionPackageRequest extends FormRequest
{
    public const MAX_SESSIONS = 200;

    /** Price ceiling in Toman. A typing guard, not a business rule. */
    public const MAX_PRICE = 1000000000;

    public function authorize(): bool
    {
        return Gate::allows('package.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'total_sessions' => Digits::toAscii((string) $this->input('total_sessions', '')),
            'price' => $this->filled('price')
                ? str_replace([',', '٬'], '', Digits::toAscii((string) $this->input('price')))
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
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')
                ->where('is_active', true)
                ->where('kind', ServiceKind::DeviceSession->value)],
            'total_sessions' => ['required', 'integer', 'min:1', 'max:'.self::MAX_SESSIONS],
            'price' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_PRICE],
            'purchased_at' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || Jalali::parseToGregorian($value) === null) {
                    $fail(__('packages.invalid_date'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => __('packages.client_required'),
            'client_id.exists' => __('packages.client_missing'),
            'service_id.required' => __('packages.service_required'),
            'service_id.exists' => __('packages.service_invalid'),
            'total_sessions.required' => __('packages.sessions_invalid'),
            'total_sessions.integer' => __('packages.sessions_invalid'),
            'total_sessions.min' => __('packages.sessions_invalid'),
            'total_sessions.max' => __('packages.sessions_invalid'),
            'price.integer' => __('packages.price_invalid'),
            'price.min' => __('packages.price_invalid'),
            'price.max' => __('packages.price_invalid'),
            'purchased_at.required' => __('packages.date_required'),
        ];
    }

    /**
     * The data for PackageService::create(). The purchase date is Gregorian.
     *
     * @return array{client_id: int, service_id: int, total_sessions: int, price: ?int, purchased_at: string}
     */
    public function packageData(): array
    {
        $gregorian = Jalali::parseToGregorian((string) $this->input('purchased_at')) ?? '';
        $price = $this->input('price');

        return [
            'client_id' => (int) $this->input('client_id'),
            'service_id' => (int) $this->input('service_id'),
            'total_sessions' => (int) $this->input('total_sessions'),
            'price' => $price === null || $price === '' ? null : (int) $price,
            'purchased_at' => $gregorian,
        ];
    }
}
