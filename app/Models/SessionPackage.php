<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceSessionStatus;
use Database\Factories\SessionPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * A package of device sessions bought by a client (spec section 6.3). The remaining sessions are always computed
 * from the done device sessions. No counter is stored. A package is never deleted: it is a financial record.
 */
class SessionPackage extends Model
{
    /** @use HasFactory<SessionPackageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'service_id',
        'total_sessions',
        'price',
        'purchased_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_sessions' => 'integer',
            'price' => 'integer',
            'purchased_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Session packages cannot be deleted.');
        });
    }

    public function totalSessions(): int
    {
        return (int) $this->getAttribute('total_sessions');
    }

    /**
     * The number of done sessions. Uses the done_sessions count when the query loaded it (withCount), so a list of
     * packages does not run one query per row.
     */
    public function doneSessions(): int
    {
        if (array_key_exists('done_sessions', $this->getAttributes())) {
            return (int) $this->getAttribute('done_sessions');
        }

        return $this->sessions()->where('status', DeviceSessionStatus::Done->value)->count();
    }

    /**
     * Total minus done (spec section 6.3). Cancelled and missed sessions do not use the package.
     */
    public function remainingSessions(): int
    {
        return $this->totalSessions() - $this->doneSessions();
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
     * @return HasMany<DeviceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(DeviceSession::class, 'package_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
