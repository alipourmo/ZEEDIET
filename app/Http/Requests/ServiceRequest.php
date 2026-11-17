<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ServiceKind;
use App\Models\Service;
use App\Support\Digits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create and edit a service (spec section 6.2). catalog.manage, admin only.
 */
class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    protected function prepareForValidation(): void
    {
        // Prices are typed with separators (150,000) and Persian digits (۱۵۰۰۰۰), so both are normalised before
        // validation. The duration is also converted, since it may be typed in Persian digits.
        $price = $this->input('default_price');

        if (is_string($price)) {
            $digits = Digits::toAscii(str_replace([',', '٬', '،', ' ', "\u{200c}"], '', trim($price)));
            $this->merge(['default_price' => $digits === '' ? null : $digits]);
        }

        $duration = $this->input('default_duration_min');

        if (is_string($duration)) {
            $this->merge(['default_duration_min' => Digits::toAscii(trim($duration))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('services', 'name')->ignore($service instanceof Service ? $service->getKey() : null)],
            'kind' => ['required', Rule::enum(ServiceKind::class)],
            'default_duration_min' => ['required', 'integer', 'between:5,480'],
            'requires_device' => ['nullable', 'boolean'],
            'requires_specialist' => ['nullable', 'boolean'],
            'default_price' => ['nullable', 'integer', 'between:0,999999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceData(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'kind' => $this->string('kind')->value(),
            'default_duration_min' => (int) $this->input('default_duration_min'),
            'requires_device' => $this->boolean('requires_device'),
            'requires_specialist' => $this->boolean('requires_specialist'),
            'default_price' => $this->filled('default_price') ? (int) $this->input('default_price') : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
