<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceKind;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A service the clinic offers (spec section 6.2). Never deleted: set is_active to false instead.
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'kind',
        'default_duration_min',
        'requires_device',
        'requires_specialist',
        'default_price',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ServiceKind::class,
            'default_duration_min' => 'integer',
            'requires_device' => 'boolean',
            'requires_specialist' => 'boolean',
            'default_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
