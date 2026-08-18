<?php

namespace App\Domains\BusinessBrain\Reasoning\Engines;

use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use App\Domains\BusinessBrain\RevenueTruth\ReceivableRealityService;
use Illuminate\Support\Collection;

class ReceivableRecoveryReasoningEngine
{
    public function __construct(
        private ReceivableRealityService $receivables
    ) {}

    public function current(): Collection
    {
        $reality =
            $this->receivables
                ->current();

        if ($reality->reportedOutstanding <= 0) {
            return collect();
        }

        return collect([
            new ExecutiveReasoning(
                type: 'receivable_recovery',

                clientId: null,

                client: null,

                title: 'Recover reported receivables',

                description: sprintf(
                    'FreeAgent reports £%s outstanding across %d invoices, with %d overdue.',
                    number_format(
                        $reality->reportedOutstanding,
                        2
                    ),
                    $reality->invoiceCount,
                    $reality->overdueInvoiceCount
                ),

                estimatedFinancialImpact: $reality->reportedOutstanding,

                estimatedEffortMinutes: 60,

                confidence: $reality->confidence,

                urgency: 90,

                score: 90,

                recommendedAction: 'Review highest value outstanding invoices and validate collection status.',

                supportingEvidence: [
                    'reported_outstanding' => $reality->reportedOutstanding,

                    'invoice_count' => $reality->invoiceCount,

                    'overdue_invoice_count' => $reality->overdueInvoiceCount,

                    'priority_invoices' => $reality->priorityInvoices,
                ]
            ),
        ]);
    }
}