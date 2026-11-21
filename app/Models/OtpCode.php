<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time portal code (spec 7.5). The code itself is never stored, only its hash. A code is used once: it is
 * consumed by a correct entry, by five wrong entries, or by a newer code for the same mobile.
 */
class OtpCode extends Model
{
    /** @var list<string> */
    protected $fillable = ['mobile', 'code_hash', 'expires_at', 'attempts', 'consumed_at', 'ip'];

    /** @var list<string> */
    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
