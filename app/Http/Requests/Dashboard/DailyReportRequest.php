<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The day for the daily report (spec section 12, S4-T6). Admin only: report.admin. The date is an optional Jalali date.
 */
final class DailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('report.admin');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && Jalali::parseToGregorian($value) === null) {
                    $fail(__('dashboard.report.invalid_date'));
                }
            }],
        ];
    }

    /**
     * The chosen day in clinic time, at midnight. Today when no date is given.
     */
    public function day(): CarbonImmutable
    {
        $gregorian = $this->filled('date') ? Jalali::parseToGregorian((string) $this->input('date')) : null;

        return CarbonImmutable::parse($gregorian ?? now('Asia/Tehran')->toDateString(), 'Asia/Tehran')->startOfDay();
    }
}
