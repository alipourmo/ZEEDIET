<?php

declare(strict_types=1);

namespace App\Http\Requests\Specialist;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The specialist's edit of a pending set's values (spec 8.4). Same decimal rules as the operator form. The route checks
 * measurement.review, and the recorder checks the range of each value.
 */
class ReviewValuesRequest extends FormRequest
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
        ];
    }
}
