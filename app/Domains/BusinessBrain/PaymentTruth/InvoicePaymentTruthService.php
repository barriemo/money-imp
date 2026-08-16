<?php

namespace App\Domains\BusinessBrain\PaymentTruth;

use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

class InvoicePaymentTruthService
{
    public function forInvoice(
        AccountingInvoice $invoice
    ): InvoicePaymentTruth {
        $allocations =
            PaymentAllocation::query()
                ->where(
                    'accounting_invoice_id',
                    $invoice->id
                )
                ->with(
                    'transaction'
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

        $bankConfirmedPaid =
            round(
                (float) $approved
                    ->sum(
                        'amount'
                    ),
                2
            );

        $suggestedPaid =
            round(
                (float) $suggested
                    ->sum(
                        'amount'
                    ),
                2
            );

        $grossAmount =
            round(
                (float) $invoice
                    ->gross_amount,
                2
            );

        $provenOutstanding =
            round(
                max(
                    0,
                    $grossAmount
                    - $bankConfirmedPaid
                ),
                2
            );

        $status =
            $this->status(
                grossAmount: $grossAmount,

                bankConfirmedPaid: $bankConfirmedPaid,

                suggestedPaid: $suggestedPaid
            );

        $accountingPaid =
            round(
                (float) $invoice
                    ->paid_amount,
                2
            );

        $accountingOutstanding =
            round(
                (float) $invoice
                    ->outstanding_amount,
                2
            );

        $accountingSaysPaid =
            $invoice->status === 'paid'
            || $accountingOutstanding === 0.0;

        $bankSaysPaid =
            $bankConfirmedPaid >= $grossAmount
            && $grossAmount > 0;

        $accountingConflict =
            $accountingSaysPaid !== $bankSaysPaid;

        return new InvoicePaymentTruth(
            invoiceId: (string) $invoice->id,

            invoiceNumber: $invoice
                ->invoice_number,

            clientId: $invoice->client_id
                ? (string) $invoice->client_id
                : null,

            client: $invoice->client
                ?->name,

            grossAmount: $grossAmount,

            bankConfirmedPaid: $bankConfirmedPaid,

            suggestedPaid: $suggestedPaid,

            provenOutstanding: $provenOutstanding,

            accountingPaid: $accountingPaid,

            accountingOutstanding: $accountingOutstanding,

            status: $status,

            accountingConflict: $accountingConflict,

            approvedPaymentCount: $approved
                ->count(),

            suggestedPaymentCount: $suggested
                ->count(),

            bankTransactionIds: $approved
                ->pluck(
                    'bank_transaction_id'
                )
                ->unique()
                ->values()
                ->all(),

            confidence: $this->confidence(
                grossAmount: $grossAmount,

                bankConfirmedPaid: $bankConfirmedPaid,

                suggestedPaid: $suggestedPaid,

                accountingConflict: $accountingConflict
            )
        );
    }

    public function current(): Collection
    {
        return AccountingInvoice::query()
            ->with(
                'client'
            )
            ->orderBy(
                'invoice_date'
            )
            ->get()
            ->map(
                fn (AccountingInvoice $invoice) => $this->forInvoice(
                    $invoice
                )
            )
            ->values();
    }

    private function status(
        float $grossAmount,
        float $bankConfirmedPaid,
        float $suggestedPaid
    ): string {
        if (
            $grossAmount > 0
            && $bankConfirmedPaid > $grossAmount
        ) {
            return 'overpaid';
        }

        if (
            $grossAmount > 0
            && $bankConfirmedPaid >= $grossAmount
        ) {
            return 'paid';
        }

        if ($bankConfirmedPaid > 0) {
            return 'part_paid';
        }

        if ($suggestedPaid > 0) {
            return 'ambiguous';
        }

        return 'unpaid';
    }

    private function confidence(
        float $grossAmount,
        float $bankConfirmedPaid,
        float $suggestedPaid,
        bool $accountingConflict
    ): int {
        if (
            $grossAmount > 0
            && $bankConfirmedPaid >= $grossAmount
        ) {
            return 100;
        }

        if ($bankConfirmedPaid > 0) {
            return 95;
        }

        if ($suggestedPaid > 0) {
            return 70;
        }

        if ($accountingConflict) {
            return 50;
        }

        return 90;
    }
}
