<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StaffRole;
use App\Support\Mobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * A new staff account (spec section 8.2). user.manage, admin only. The password is generated, not typed.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('user.manage');
    }

    protected function prepareForValidation(): void
    {
        $mobile = $this->input('mobile');

        if (is_string($mobile)) {
            $this->merge(['mobile' => Mobile::normalize($mobile) ?? trim($mobile)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,mobile'],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'mobile' => $this->string('mobile')->value(),
            'role' => $this->string('role')->value(),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
