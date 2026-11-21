<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\Gender;
use App\Enums\PregnancyStatus;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * A client (spec section 6.1). Soft delete only: forceDelete() is refused.
 */
class Client extends Model implements AuthenticatableContract
{
    /** @use HasFactory<ClientFactory> */
    use Authenticatable, HasFactory, SoftDeletes;

    // A client signs in to the portal with a one-time code (spec 7.5), so there is no password to check.

    /** @var list<string> */
    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'mobile',
        'birth_date',
        'gender',
        'height_cm',
        'pregnancy_status',
        'referral_source',
        'internal_note',
        'consent_data_at',
        'consent_photos_at',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'height_cm' => 'decimal:1',
            'gender' => Gender::class,
            'pregnancy_status' => PregnancyStatus::class,
            'status' => ClientStatus::class,
            'consent_data_at' => 'datetime',
            'consent_photos_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (): void {
            throw new LogicException('Clients are soft-delete only; hard deletes are not allowed.');
        });
    }

    /**
     * @return HasOne<ClientMedical, $this>
     */
    public function medical(): HasOne
    {
        return $this->hasOne(ClientMedical::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
