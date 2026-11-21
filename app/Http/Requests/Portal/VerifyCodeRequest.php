<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The code is five digits (spec 7.5). Persian digits are already ASCII by the time these rules run (NormalizeInput).
 */
class VerifyCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'digits:5']];
    }

    public function code(): string
    {
        return (string) $this->input('code');
    }
}
