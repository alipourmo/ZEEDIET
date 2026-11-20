<?php

declare(strict_types=1);

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The device parameters and note for one session (spec 6.3 and 8.3). The route checks session.record.
 */
class SessionRecordRequest extends FormRequest
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
            'intensity' => ['nullable', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:50'],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:240'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'intensity' => __('sessions.field_intensity'),
            'area' => __('sessions.field_area'),
            'duration_min' => __('sessions.field_duration'),
            'note' => __('sessions.field_note'),
        ];
    }
}
