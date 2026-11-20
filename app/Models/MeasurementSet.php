<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeasurementSetStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * One dated set of measurements (spec section 6.4). A set is never deleted: it is part of the client's record.
 */
class MeasurementSet extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'measured_at',
        'recorded_by',
        'status',
        'reviewed_by',
        'published_at',
        'supersedes_id',
        'superseded_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_at' => 'date',
            'status' => MeasurementSetStatus::class,
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Measurement sets cannot be deleted.');
        });
    }

    /**
     * The sets a client sees and the charts use: published, and not superseded (spec 7.4).
     *
     * @param  Builder<MeasurementSet>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', MeasurementSetStatus::Published->value)->whereNull('superseded_at');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return HasMany<Measurement, $this>
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }
}
