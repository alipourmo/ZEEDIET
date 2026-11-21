<?php

declare(strict_types=1);

namespace App\Http\Requests\Specialist;

use App\Support\Digits;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A note on a client, with an optional next follow-up date typed in Jalali (spec 6.5 and 8.4). Needs note.manage.
 */
final class StoreSpecialistNoteRequest extends FormRequest
{
    public const MAX_BODY = 2000;

    public function authorize(): bool
    {
        return Gate::allows('note.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body', '')),
            'next_follow_up_on' => trim(Digits::toAscii((string) $this->input('next_follow_up_on', ''))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'next_follow_up_on' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && $value !== '' && Jalali::parseToGregorian($value) === null) {
                    $fail(__('notes.date_invalid'));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => __('notes.body_required'),
            'body.max' => __('notes.body_too_long', ['max' => Digits::toPersian(self::MAX_BODY)]),
        ];
    }

    /**
     * The follow-up date at the start of the clinic day, or null when it was left empty.
     */
    public function followUpOn(): ?CarbonImmutable
    {
        $value = (string) $this->input('next_follow_up_on', '');

        if ($value === '') {
            return null;
        }

        $gregorian = Jalali::parseToGregorian($value);

        return $gregorian === null ? null : CarbonImmutable::parse($gregorian, 'Asia/Tehran')->startOfDay();
    }
}
