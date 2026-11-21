<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A slimming device (spec section 6.2). Its contraindications are compared with the client's when a session is
 * recorded (S3). Never deleted: set is_active to false instead.
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'model',
        'contraindications',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contraindications' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
