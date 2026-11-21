<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Scheduling\SlotMath;
use App\Support\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * The weekly hours of one resource (spec section 8.2). Seven days, each either off or with a start and end time.
 */
class UpdateScheduleRequest extends FormRequest
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
        $rules = [];

        for ($day = 0; $day < Weekday::COUNT; $day++) {
            $rules["days.$day.enabled"] = ['nullable', 'boolean'];
            $rules["days.$day.start"] = ['nullable', 'date_format:H:i'];
            $rules["days.$day.end"] = ['nullable', 'date_format:H:i'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'days.*.start.date_format' => __('scheduling.invalid_time'),
            'days.*.end.date_format' => __('scheduling.invalid_time'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            for ($day = 0; $day < Weekday::COUNT; $day++) {
                if (! $this->boolean("days.$day.enabled")) {
                    continue;
                }

                $start = $this->input("days.$day.start");
                $end = $this->input("days.$day.end");

                if (! is_string($start) || $start === '' || ! is_string($end) || $end === '') {
                    $v->errors()->add("days.$day.end", __('scheduling.hours_required'));

                    continue;
                }

                if (SlotMath::toMinutes($start) >= SlotMath::toMinutes($end)) {
                    $v->errors()->add("days.$day.end", __('scheduling.end_after_start'));
                }
            }
        });
    }

    /**
     * The rows to store: only the days that are switched on, with times as HH:MM:SS.
     *
     * @return list<array{weekday: int, start_time: string, end_time: string}>
     */
    public function rows(): array
    {
        $rows = [];

        for ($day = 0; $day < Weekday::COUNT; $day++) {
            if (! $this->boolean("days.$day.enabled")) {
                continue;
            }

            $rows[] = [
                'weekday' => $day,
                'start_time' => (string) $this->input("days.$day.start").':00',
                'end_time' => (string) $this->input("days.$day.end").':00',
            ];
        }

        return $rows;
    }
}
