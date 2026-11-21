<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create and edit a device (spec section 6.2). catalog.manage, admin only.
 */
class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $device = $this->route('device');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('devices', 'name')->ignore($device instanceof Device ? $device->getKey() : null)],
            'model' => ['nullable', 'string', 'max:120'],
            'contraindications' => ['nullable', 'array'],
            'contraindications.*' => ['string', Rule::in(config('clinic.contraindication_keys'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deviceData(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim((string) $this->input($key)) : null;

        return [
            'name' => $this->string('name')->trim()->value(),
            'model' => $text('model'),
            'contraindications' => array_values(array_unique((array) $this->input('contraindications', []))),
            'notes' => $text('notes'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
