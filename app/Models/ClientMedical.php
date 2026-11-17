<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Medical record of a client (spec section 6.1). Read and written only through ClientService, which audits each access.
 * Hard deletes are refused.
 */
class ClientMedical extends Model
{
    protected $table = 'client_medical';

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'food_allergies',
        'diseases',
        'surgeries',
        'medications',
        'genetics_notes',
        'diet_relevant_notes',
        'device_contraindications',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_contraindications' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Medical records cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
