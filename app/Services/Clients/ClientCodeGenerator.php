<?php

declare(strict_types=1);

namespace App\Services\Clients;

use Illuminate\Support\Facades\DB;

/**
 * Hands out client codes C-000001, C-000002 ... (spec section 6.1). A codes row is locked while it is incremented,
 * so two requests never get the same code. Codes are never reused, even after a soft delete.
 */
final class ClientCodeGenerator
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            $current = DB::table('sequences')
                ->where('name', 'client_code')
                ->lockForUpdate()
                ->value('value');

            $next = ((int) $current) + 1;

            DB::table('sequences')->where('name', 'client_code')->update(['value' => $next]);

            return sprintf('C-%06d', $next);
        });
    }
}
