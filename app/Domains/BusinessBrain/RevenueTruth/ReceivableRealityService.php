<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use App\Models\AccountingInvoice;

class ReceivableRealityService
{
    public function current(): ReceivableReality
    {
        $invoices =
            AccountingInvoice::query()
                ->where(
                    'outstanding_amount',
                    '>',
                    0
                )
                ->get();

        $overdue =
            $invoices
                ->where(
                    'status',
                    'overdue'
                );

        return new ReceivableReality(
            reportedOutstanding: (float) $invoices
                ->sum('outstanding_amount'),

            invoiceCount: $invoices
                ->count(),

            overdueInvoiceCount: $overdue
                ->count(),

            confidence: 0,

            priorityInvoices: $invoices
                ->sortByDesc(
                    'outstanding_amount'
                )
                ->take(10)
                ->map(
                    fn (AccountingInvoice $invoice) => [
                        'client_id' => $invoice->client_id,

                        'invoice_number' => $invoice->invoice_number,

                        'amount' => (float) $invoice
                            ->outstanding_amount,
                    ]
                )
                ->values()
                ->toArray()
        );
    }
}
