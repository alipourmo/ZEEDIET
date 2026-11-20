<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceSessionStatus;
use Database\Factories\DeviceSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One treatment on a device (spec section 6.3). Never deleted: a session is part of the client's record.
 */
class DeviceSession extends Model
{
    /** @use HasFactory<DeviceSessionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'appointment_id',
        'package_id',
        'device_id',
        'operator_id',
        'session_number',
        'status',
        'parameters',
        'note',
        'performed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_number' => 'integer',
            'status' => DeviceSessionStatus::class,
            'parameters' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Device sessions cannot be deleted.');
        });
    }

    /**
     * @param  Builder<DeviceSession>  $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->where('status', DeviceSessionStatus::Done->value);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<SessionPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SessionPackage::class, 'package_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
