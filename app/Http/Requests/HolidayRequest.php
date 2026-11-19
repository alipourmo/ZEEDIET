<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Holiday;
use App\Support\Jalali;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * An official holiday (spec section 6.2). The date is typed in Jalali and stored as Gregorian.
 */
class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $gregorian = Jalali::parseToGregorian((string) $this->input('date'));

            if ($gregorian === null) {
                $v->errors()->add('date', __('scheduling.invalid_date'));

                return;
            }

            $query = Holiday::query()->where('date', $gregorian);
            $current = $this->route('holiday');

            if ($current instanceof Holiday) {
                $query->whereKeyNot($current->getKey());
            }

            if ($query->exists()) {
                $v->errors()->add('date', __('scheduling.holiday_exists'));
            }
        });
    }

    /**
     * @return array{date: string, title: string}
     */
    public function holidayData(): array
    {
        return [
            'date' => (string) Jalali::parseToGregorian((string) $this->input('date')),
            'title' => $this->string('title')->trim()->value(),
        ];
    }
}
