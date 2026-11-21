<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An SMS template (spec section 9). The body is edited by the admin; the key is fixed.
 */
class SmsTemplate extends Model
{
    /** @var list<string> */
    protected $fillable = ['key', 'body', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
