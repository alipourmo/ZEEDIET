<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An official holiday. The clinic is closed on it (spec section 7.1).
 */
class Holiday extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'date',
        'title',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
