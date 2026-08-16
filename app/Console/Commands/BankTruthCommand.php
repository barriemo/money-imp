<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\BankTruth\BankTransactionDeduplicationService;
use App\Models\BankTransaction;
use Illuminate\Console\Command;

class BankTruthCommand extends Command
{
    protected $signature = 'business:bank-truth';

    protected $description = 'Show canonical bank transaction truth';

    public function handle(
        BankTransactionDeduplicationService $truth
    ): int {
        $transactions = $truth->current();

        $duplicates =
            $transactions
                ->filter(
                    fn ($transaction) => $transaction->resolution === 'duplicate_evidence'
                );

        $this->line('');
        $this->line('Money Imp Bank Truth');
        $this->line('');

        $this->line(
            'Raw payment rows: '.
            number_format(
                BankTransaction::query()
                    ->where(
                        'transaction_type',
                        'customer_payment'
                    )
                    ->where(
                        'amount',
                        '>',
                        0
                    )
                    ->count()
            )
        );

        $this->line(
            'Canonical payment events: '.
            number_format(
                $transactions->count()
            )
        );

        $this->line(
            'Duplicate evidence groups: '.
            number_format(
                $duplicates->count()
            )
        );

        $this->line(
            'Duplicate evidence value: £'.
            number_format(
                (float) $duplicates->sum('amount'),
                2
            )
        );

        $this->line('');

        return self::SUCCESS;
    }
}
