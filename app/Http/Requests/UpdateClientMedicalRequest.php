<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Medical record (spec section 6.1). Only admin and specialist may write it (medical.update).
 */
class UpdateClientMedicalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('medical.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'food_allergies' => ['nullable', 'string', 'max:5000'],
            'diseases' => ['nullable', 'string', 'max:5000'],
            'surgeries' => ['nullable', 'string', 'max:5000'],
            'medications' => ['nullable', 'string', 'max:5000'],
            'genetics_notes' => ['nullable', 'string', 'max:5000'],
            'diet_relevant_notes' => ['nullable', 'string', 'max:5000'],
            'device_contraindications' => ['nullable', 'array'],
            'device_contraindications.*' => ['string', Rule::in(config('clinic.contraindication_keys'))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function medicalData(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim((string) $this->input($key)) : null;

        return [
            'food_allergies' => $text('food_allergies'),
            'diseases' => $text('diseases'),
            'surgeries' => $text('surgeries'),
            'medications' => $text('medications'),
            'genetics_notes' => $text('genetics_notes'),
            'diet_relevant_notes' => $text('diet_relevant_notes'),
            'device_contraindications' => array_values(array_unique((array) $this->input('device_contraindications', []))),
        ];
    }
}
