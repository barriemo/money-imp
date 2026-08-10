<?php

namespace App\Console\Commands;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentContactSyncService;
use App\Models\ExternalConnection;
use Illuminate\Console\Command;

class SyncFreeAgentContacts extends Command
{
    protected $signature = 'money-imp:sync-freeagent-contacts';

    protected $description = 'Sync FreeAgent clients into Money Imp';

    public function handle(
        FreeAgentContactSyncService $sync
    ): int {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            $this->error(
                'No connected FreeAgent integration was found.'
            );

            return self::FAILURE;
        }

        $this->info('Syncing FreeAgent clients...');

        $run = $sync->sync($connection);

        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Seen', $run->records_seen],
                ['Created', $run->records_created],
                ['Updated', $run->records_updated],
                ['Skipped', $run->records_skipped],
                ['Failed', $run->records_failed],
            ]
        );

        $this->newLine();

        $this->info(
            'FreeAgent client sync: '.$run->status
        );

        return $run->status === 'failed'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
