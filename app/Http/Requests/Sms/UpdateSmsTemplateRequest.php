<?php

declare(strict_types=1);

namespace App\Http\Requests\Sms;

use App\Enums\SmsTemplateKey;
use App\Models\SmsTemplate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Editing an SMS template (spec section 9). Admin only: sms.templates.manage. Only the placeholders the template may
 * use are accepted, so the text cannot carry a value the system never fills in.
 */
final class UpdateSmsTemplateRequest extends FormRequest
{
    public const MAX_LENGTH = 300;

    public function authorize(): bool
    {
        return Gate::allows('sms.templates.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_LENGTH, $this->placeholders(...)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('sms.errors.body_required'),
            'body.max' => __('sms.errors.body_max'),
        ];
    }

    /**
     * A placeholder must be one the template may use. The OTP template must carry the code.
     */
    private function placeholders(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $template = $this->route('template');

        if (! $template instanceof SmsTemplate) {
            return;
        }

        $key = SmsTemplateKey::tryFrom($template->key);

        if ($key === null) {
            return;
        }

        preg_match_all('/\{([a-z_]+)\}/', $value, $matches);

        foreach (array_unique($matches[1]) as $name) {
            if (! in_array($name, $key->allowedPlaceholders(), true)) {
                $fail($name === 'code'
                    ? __('sms.errors.placeholder_code')
                    : __('sms.errors.placeholder_unknown', ['name' => '{'.$name.'}']));

                return;
            }
        }

        if ($key->redactsCode() && ! str_contains($value, '{code}')) {
            $fail(__('sms.errors.otp_needs_code'));
        }
    }
}
