<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A specialist's note on a client (spec section 6.5). Notes are append-only: they cannot be changed or deleted, so the
 * record of what was written and when stays intact.
 */
class SpecialistNote extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'specialist_id',
        'body',
        'next_follow_up_on',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Specialist notes are append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('Specialist notes are append-only.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_follow_up_on' => 'date',
        ];
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
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }
}
