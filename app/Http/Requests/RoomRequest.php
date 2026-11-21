<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Create and edit a room (spec section 6.2). catalog.manage, admin only.
 */
class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('catalog.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('rooms', 'name')->ignore($room instanceof Room ? $room->getKey() : null)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function roomData(): array
    {
        return [
            'name' => $this->string('name')->trim()->value(),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
