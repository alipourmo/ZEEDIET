<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booked visit (spec section 6.3). Never hard-deleted: cancelling or marking no-show keeps the row.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'service_id',
        'specialist_id',
        'device_id',
        'room_id',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'cancel_reason',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
        ];
    }

    /**
     * The current status with its enum type. Larastan reads the cast column as a string, so the callers use this.
     */
    public function currentStatus(): AppointmentStatus
    {
        /** @var AppointmentStatus $status */
        $status = $this->getAttribute('status');

        return $status;
    }

    /**
     * The start or end as a Carbon instance, with its type made explicit (see currentStatus()).
     */
    public function time(string $column): CarbonInterface
    {
        /** @var CarbonInterface $time */
        $time = $this->getAttribute($column);

        return $time;
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
