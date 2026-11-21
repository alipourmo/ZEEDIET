<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Clients\ClientPurger;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Hard-deletes a soft-deleted client after the retention period (spec section 10.10). Without --confirm it only shows
 * what would be removed. With --confirm it asks once more, then deletes and writes an audit row.
 */
class ClientsPurge extends Command
{
    protected $signature = 'clients:purge {client : The client id} {--confirm : Delete for real. Without it, only the plan is shown}';

    protected $description = 'Permanently delete a soft-deleted client after the retention period (writes an audit row).';

    public function handle(ClientPurger $purger): int
    {
        $clientId = (int) $this->argument('client');

        try {
            $plan = $purger->plan($clientId);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Table', 'Rows'], collect($plan)->map(fn (int $rows, string $table): array => [$table, $rows])->values()->all());

        if (! $this->option('confirm')) {
            $this->warn('Dry run. Nothing was deleted. Run again with --confirm to delete these rows permanently.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Permanently delete client {$clientId} and the rows above? This cannot be undone.", false)) {
            $this->info('Cancelled. Nothing was deleted.');

            return self::SUCCESS;
        }

        $purger->purge($clientId);
        $this->info("Client {$clientId} was purged. The audit log keeps a record of the purge.");

        return self::SUCCESS;
    }
}
