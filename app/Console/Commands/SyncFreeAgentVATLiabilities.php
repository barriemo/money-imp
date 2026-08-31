<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\RevenueTruth\FreeAgentVATLiabilitySyncService;
use App\Models\ExternalConnection;
use Illuminate\Console\Command;

class SyncFreeAgentVATLiabilities extends Command
{
    protected $signature = 'money-imp:sync-freeagent-vat';

    protected $description =
        'Sync reported VAT liability evidence from FreeAgent';

    public function handle(
        FreeAgentVATLiabilitySyncService $sync
    ): int {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            $this->error(
                'No connected FreeAgent integration found.'
            );

            return self::FAILURE;
        }

        $this->info(
            'Syncing FreeAgent VAT liability evidence...'
        );

        $result = $sync->sync($connection);

        $this->table(
            ['Metric', 'Value'],
            [
                ['VAT returns', $result['returns']],
                ['Payments seen', $result['payments_seen']],
                ['Reported open', $result['open']],
                ['Reported closed', $result['closed']],
                ['Ignored', $result['ignored']],
            ]
        );

        return self::SUCCESS;
    }
}
