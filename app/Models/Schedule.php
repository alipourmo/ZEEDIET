<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleResource;
use Illuminate\Database\Eloquent\Model;

/**
 * One working window of a resource on one weekday (spec section 6.2). weekday: 0 = Saturday ... 6 = Friday.
 */
class Schedule extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'resource_type',
        'resource_id',
        'weekday',
        'start_time',
        'end_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_type' => ScheduleResource::class,
            'resource_id' => 'integer',
            'weekday' => 'integer',
        ];
    }
}
