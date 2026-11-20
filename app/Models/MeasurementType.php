<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A kind of measurement, such as weight or waist (spec section 6.4). The unit decides the accepted range (config
 * clinic.measurement_ranges).
 */
class MeasurementType extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
        'label_key',
        'unit',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
