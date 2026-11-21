<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Support\Jalali;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the audit log viewer (spec section 8.2). Dates are typed as Jalali (YYYY/MM/DD).
 * The route's can:audit.view middleware does the access check.
 */
class AuditFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'integer', 'min:1'],
            'client' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'from' => ['nullable', 'string', 'max:10', $this->jalaliDate()],
            'to' => ['nullable', 'string', 'max:10', $this->jalaliDate()],
        ];
    }

    /**
     * The filter values ready for the query: ints, an enum, and Gregorian Y-m-d dates.
     *
     * @return array{actor: ?int, client: ?int, action: ?AuditAction, from: ?string, to: ?string}
     */
    public function filters(): array
    {
        $from = $this->filled('from') ? Jalali::parseToGregorian($this->string('from')->value()) : null;
        $to = $this->filled('to') ? Jalali::parseToGregorian($this->string('to')->value()) : null;

        return [
            'actor' => $this->filled('actor') ? $this->integer('actor') : null,
            'client' => $this->filled('client') ? $this->integer('client') : null,
            'action' => $this->filled('action') ? AuditAction::from($this->string('action')->value()) : null,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function jalaliDate(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && $value !== '' && Jalali::parseToGregorian((string) $value) === null) {
                $fail(__('audit.invalid_date'));
            }
        };
    }
}
