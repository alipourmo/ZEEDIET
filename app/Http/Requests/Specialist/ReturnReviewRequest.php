<?php

declare(strict_types=1);

namespace App\Http\Requests\Specialist;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Returning a set to the operator needs a comment (spec 8.4). The route checks measurement.review.
 */
class ReturnReviewRequest extends FormRequest
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
            'comment' => ['required', 'string', 'max:500'],
        ];
    }
}
