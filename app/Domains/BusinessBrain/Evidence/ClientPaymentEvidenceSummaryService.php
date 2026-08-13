<?php

namespace App\Domains\BusinessBrain\Evidence;

use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\PaymentAllocation;

class ClientPaymentEvidenceSummaryService
{
    public function forClient(
        Client $client
    ): ClientPaymentEvidenceSummary {
        $invoices =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->get();

        $paidInvoices =
            $invoices
                ->filter(
                    fn (AccountingInvoice $invoice) => $invoice->status === 'paid'
                        || (float) $invoice->outstanding_amount === 0.0
                );

        $invoiceIds =
            $invoices
                ->pluck(
                    'id'
                );

        $allocations =
            PaymentAllocation::query()
                ->whereIn(
                    'accounting_invoice_id',
                    $invoiceIds
                )
                ->get();

        $approved =
            $allocations
                ->whereIn(
                    'status',
                    [
                        'approved',
                        'imported',
                    ]
                );

        $suggested =
            $allocations
                ->where(
                    'status',
                    'suggested'
                );

        $approvedInvoiceIds =
            $approved
                ->pluck(
                    'accounting_invoice_id'
                )
                ->unique();

        $unsupportedPaidInvoices =
            $paidInvoices
                ->reject(
                    fn (AccountingInvoice $invoice) => $approvedInvoiceIds
                        ->contains(
                            $invoice->id
                        )
                )
                ->count();

        return new ClientPaymentEvidenceSummary(
            clientId: (string) $client->id,

            client: $client->name,

            invoiceCount: $invoices->count(),

            paidInvoiceCount: $paidInvoices->count(),

            approvedPaymentAllocationCount: $approved->count(),

            suggestedPaymentAllocationCount: $suggested->count(),

            paidInvoicesWithoutApprovedEvidence: $unsupportedPaidInvoices,

            approvedPaymentValue: (float) $approved
                ->sum(
                    'amount'
                ),

            confidence: $this->confidence(
                paidInvoiceCount: $paidInvoices->count(),

                unsupportedPaidInvoices: $unsupportedPaidInvoices
            )
        );
    }

    private function confidence(
        int $paidInvoiceCount,
        int $unsupportedPaidInvoices
    ): int {
        if ($paidInvoiceCount === 0) {
            return 100;
        }

        $supported =
            $paidInvoiceCount
            - $unsupportedPaidInvoices;

        return (int) round(
            ($supported / $paidInvoiceCount)
            * 100
        );
    }
}
