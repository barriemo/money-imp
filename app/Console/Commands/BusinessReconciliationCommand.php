<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Reconciliation\ReconciliationSummaryService;
use Illuminate\Console\Command;

class BusinessReconciliationCommand extends Command
{
    protected $signature = 'business:reconciliation';

    protected $description = 'Display the current business reconciliation position';

    public function handle(
        ReconciliationSummaryService $reconciliation
    ): int {
        $summary =
            $reconciliation
                ->current();

        $this->newLine();

        $this->info(
            'Money Imp Reconciliation'
        );

        $this->newLine();

        $this->line(
            sprintf(
                'Positive bank movements: %d worth £%s',
                $summary->positiveTransactionCount,
                number_format(
                    $summary->positiveTransactionValue,
                    2
                )
            )
        );

        $this->line(
            sprintf(
                'Likely customer receipts: %d worth £%s',
                $summary->customerPaymentCount,
                number_format(
                    $summary->customerPaymentValue,
                    2
                )
            )
        );

        $this->newLine();

        $this->line(
            sprintf(
                'Confirmed allocations: %d worth £%s',
                $summary->confirmedAllocationCount,
                number_format(
                    $summary->confirmedAllocationValue,
                    2
                )
            )
        );

        $this->line(
            sprintf(
                'Suggested allocations: %d worth £%s',
                $summary->suggestedAllocationCount,
                number_format(
                    $summary->suggestedAllocationValue,
                    2
                )
            )
        );

        $this->newLine();

        $this->line(
            sprintf(
                'Ignored non-client inflows: %d worth £%s',
                $summary->ignoredTransactionCount,
                number_format(
                    $summary->ignoredTransactionValue,
                    2
                )
            )
        );

        $this->line(
            sprintf(
                'Still unmatched: %d worth £%s',
                $summary->unmatchedTransactionCount,
                number_format(
                    $summary->unmatchedTransactionValue,
                    2
                )
            )
        );

        $this->newLine();

        $this->line(
            sprintf(
                'Invoices with payment candidates: %d',
                $summary->matchedInvoiceCount
            )
        );

        $this->line(
            sprintf(
                'Invoices without payment candidates: %d',
                $summary->unmatchedInvoiceCount
            )
        );

        $this->line(
            sprintf(
                'Confirmed reconciliation coverage: %d%%',
                $summary->reconciliationCoverage
            )
        );

        return self::SUCCESS;
    }
}
