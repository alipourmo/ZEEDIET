<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Support\Mobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The sign-in form asks for a mobile (spec 7.5). A badly formed number is refused here; whether the number belongs to a
 * client is never checked here, so the answer cannot reveal it.
 */
class RequestCodeRequest extends FormRequest
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
        return ['mobile' => ['required', 'string', 'max:32']];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $mobile = $this->input('mobile');

                if (is_string($mobile) && Mobile::normalize($mobile) === null) {
                    $validator->errors()->add('mobile', __('portal.mobile_invalid'));
                }
            },
        ];
    }

    /**
     * The normalised mobile (09xxxxxxxxx). Only valid after validation has passed.
     */
    public function mobile(): string
    {
        return (string) Mobile::normalize((string) $this->input('mobile'));
    }
}
