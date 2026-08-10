<?php

namespace App\Console\Commands;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentBankAccountSyncService;
use App\Models\BankAccount;
use App\Models\ExternalConnection;
use Illuminate\Console\Command;

class SyncFreeAgentBankAccounts extends Command
{
    protected $signature = 'money-imp:sync-freeagent-bank-accounts';

    protected $description = 'Sync FreeAgent bank accounts into Money Imp';

    public function handle(
        FreeAgentBankAccountSyncService $sync
    ): int {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            $this->error('No connected FreeAgent integration found.');

            return self::FAILURE;
        }

        $this->info('Syncing FreeAgent bank accounts...');

        $run = $sync->sync($connection);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Seen', $run->records_seen],
                ['Created', $run->records_created],
                ['Updated', $run->records_updated],
                ['Failed', $run->records_failed],
            ]
        );

        $this->line(
            'Bank accounts in Money Imp: '.BankAccount::count()
        );

        return $run->status === 'failed'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
