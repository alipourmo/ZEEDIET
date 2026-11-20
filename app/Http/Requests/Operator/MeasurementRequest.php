<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The operator's measurement form (spec 8.3). Persian decimal separators are accepted (Persian digits are converted
 * by NormalizeInput). The route checks measurement.record.
 */
class MeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $values = $this->input('values');

        if (! is_array($values)) {
            return;
        }

        $normalized = [];

        foreach ($values as $key => $value) {
            $normalized[$key] = is_string($value) ? str_replace(['٫', '،', ','], '.', trim($value)) : $value;
        }

        $this->merge(['values' => $normalized]);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
