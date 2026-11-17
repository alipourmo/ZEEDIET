<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff login form (spec section 7.6). Digits are already ASCII by the time these rules run (NormalizeInput).
 */
class LoginRequest extends FormRequest
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
        return [
            'mobile' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
