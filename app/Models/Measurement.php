<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One value in a measurement set (spec section 6.4). Value is decimal(6,2), kept as a string by the cast.
 */
class Measurement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'measurement_set_id',
        'measurement_type_id',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<MeasurementSet, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(MeasurementSet::class, 'measurement_set_id');
    }

    /**
     * @return BelongsTo<MeasurementType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MeasurementType::class, 'measurement_type_id');
    }
}
