<?php

namespace App\Console\Commands;

use App\Domains\Reconciliation\Services\ReconciliationCandidateService;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateReconciliationCandidates extends Command
{
    protected $signature = 'money-imp:reconciliation-candidates';

    protected $description = 'Rebuild reconciliation suggestions from unmatched bank receipts';

    public function handle(
        ReconciliationCandidateService $service
    ): int {
        $this->info('Rebuilding reconciliation candidates...');

        DB::transaction(function (): void {
            PaymentAllocation::query()
                ->where('status', 'suggested')
                ->delete();

            BankTransaction::query()
                ->where('match_status', 'suggested')
                ->update([
                    'client_id' => null,
                    'match_status' => 'unmatched',
                    'match_confidence' => null,
                    'transaction_type' => 'imported',
                ]);
        });

        $stats = $service->generate();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Considered', $stats['considered']],
                ['Classified non-client', $stats['classified_non_client']],
                ['Client matches', $stats['client_matches']],
                ['Invoice matches', $stats['invoice_matches']],
                ['Ambiguous', $stats['ambiguous']],
                ['Still unmatched', $stats['unmatched']],
            ]
        );

        return self::SUCCESS;
    }
}
