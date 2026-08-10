<?php

namespace App\Console\Commands;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentInvoiceSyncService;
use App\Models\AccountingInvoice;
use App\Models\ExternalConnection;
use Illuminate\Console\Command;

class SyncFreeAgentInvoices extends Command
{
    protected $signature = 'money-imp:sync-freeagent-invoices';

    protected $description = 'Sync FreeAgent invoices into Money Imp';

    public function handle(
        FreeAgentInvoiceSyncService $sync
    ): int {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            $this->error('No connected FreeAgent integration found.');

            return self::FAILURE;
        }

        $this->info('Syncing FreeAgent invoices...');

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
            'Invoices in Money Imp: '.AccountingInvoice::count()
        );

        $this->info(
            'FreeAgent invoice sync: '.$run->status
        );

        return $run->status === 'failed'
            ? self::FAILURE
            : self::SUCCESS;
    }
}
