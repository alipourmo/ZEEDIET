<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * A payment or a reversing entry (spec sections 6.5 and 8.2). Rows are append-only: a payment cannot be updated or
 * deleted. A mistake is fixed by a reversal, a new row with the negative amount and reverses_id set. The client's
 * balance is the sum of amount over all rows.
 */
class Payment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'appointment_id',
        'package_id',
        'amount',
        'method',
        'paid_at',
        'reference',
        'note',
        'reverses_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Payments cannot be changed. Record a reversal instead.');
        });

        static::deleting(function (): void {
            throw new LogicException('Payments cannot be deleted. Record a reversal instead.');
        });
    }

    public function isReversal(): bool
    {
        return $this->getAttribute('reverses_id') !== null;
    }

    /**
     * Whether a reversing entry exists for this payment. Uses the eager-loaded reversal when present, so a list does
     * not run one query per row.
     */
    public function isReversed(): bool
    {
        if ($this->relationLoaded('reversal')) {
            return $this->getRelation('reversal') !== null;
        }

        return static::query()->where('reverses_id', $this->getKey())->exists();
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
     * The reversing entry for this payment, if one was recorded.
     *
     * @return HasOne<Payment, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(Payment::class, 'reverses_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
