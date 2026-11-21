<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * One status change of an appointment (spec section 6.3). Only created_at, no updated_at: the history is append-only.
 */
class AppointmentStatusHistory extends Model
{
    public $timestamps = false;

    protected $table = 'appointment_status_history';

    /** @var list<string> */
    protected $fillable = [
        'appointment_id',
        'from_status',
        'to_status',
        'changed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
