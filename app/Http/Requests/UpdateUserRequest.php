<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StaffRole;
use App\Models\User;
use App\Support\Mobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edit a staff account (spec section 8.2). An admin cannot deactivate their own account or change their own role,
 * so the last working admin cannot lock themselves out.
 */
class UpdateUserRequest extends FormRequest
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
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'mobile')->ignore($target instanceof User ? $target->getKey() : null)],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $target = $this->route('user');
            $actor = $this->user();

            if (! $target instanceof User || ! $actor instanceof User || $target->isNot($actor)) {
                return;
            }

            if ($this->string('role')->value() !== $actor->role->value) {
                $v->errors()->add('role', __('users.self_role'));
            }

            if (! $this->boolean('is_active')) {
                $v->errors()->add('is_active', __('users.self_deactivate'));
            }
        });
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
