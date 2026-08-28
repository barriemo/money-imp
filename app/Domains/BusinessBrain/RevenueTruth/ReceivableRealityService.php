<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;

class ReceivableRealityService
{
    public function current(): ReceivableReality
    {
        $ledgerInvoices = AccountingInvoice::query()
            ->where('outstanding_amount', '>', 0)
            ->get();

        $recoverableInvoices = $ledgerInvoices
            ->whereNotIn('status', [
                'draft',
                'written_off',
                'paid',
                'refunded',
                'zero_value',
            ]);

        $today = now()->startOfDay();

        $overdue = $recoverableInvoices
            ->filter(
                fn (AccountingInvoice $invoice) => $invoice->due_date !== null
                    && $invoice->due_date->startOfDay()->lte($today)
            );

        $confirmedAllocations = PaymentAllocation::query()
            ->whereIn('status', [
                'approved',
                'imported',
            ]);

        $suggestedAllocations = PaymentAllocation::query()
            ->where('status', 'suggested');

        return new ReceivableReality(
            reportedOutstanding: (float) $recoverableInvoices
                ->sum('outstanding_amount'),

            invoiceCount: $recoverableInvoices
                ->count(),

            overdueInvoiceCount: $overdue
                ->count(),

            confidence: 100,

            priorityInvoices: $overdue
                ->sortByDesc('outstanding_amount')
                ->take(10)
                ->map(
                    fn (AccountingInvoice $invoice) => [
                        'client_id' => $invoice->client_id,

                        'invoice_number' => $invoice->invoice_number,

                        'amount' => (float) $invoice->outstanding_amount,

                        'status' => $invoice->status,

                        'due_date' => $invoice->due_date?->toDateString(),
                    ]
                )
                ->values()
                ->toArray(),

            ledgerOutstanding: (float) $ledgerInvoices
                ->sum('outstanding_amount'),

            writtenOffAmount: (float) $ledgerInvoices
                ->where('status', 'written_off')
                ->sum('outstanding_amount'),

            draftAmount: (float) $ledgerInvoices
                ->where('status', 'draft')
                ->sum('outstanding_amount'),
            confirmedPaymentEvidence: round(
                (float) $confirmedAllocations->sum('amount'),
                2
            ),
            suggestedPaymentEvidence: round(
                (float) $suggestedAllocations->sum('amount'),
                2
            ),
            confirmedPaymentCount: $confirmedAllocations->count(),
            suggestedPaymentCount: $suggestedAllocations->count()
        );
    }
}
