<?php

namespace App\Console\Commands;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentBankTransactionSyncService;
use App\Models\BankTransaction;
use App\Models\ExternalConnection;
use Illuminate\Console\Command;

class SyncFreeAgentBankTransactions extends Command
{
    protected $signature = 'money-imp:sync-freeagent-bank-transactions';

    protected $description = 'Sync FreeAgent bank transactions into Money Imp';

    public function handle(
        FreeAgentBankTransactionSyncService $sync
    ): int {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            $this->error('No connected FreeAgent integration found.');

            return self::FAILURE;
        }

        $this->info('Syncing FreeAgent bank transactions...');

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

        $this->newLine();

        $this->line(
            'Bank transactions in Money Imp: '.BankTransaction::count()
        );

        $this->info(
            'FreeAgent bank transaction sync: '.$run->status
        );

        return $run->status === 'failed'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
