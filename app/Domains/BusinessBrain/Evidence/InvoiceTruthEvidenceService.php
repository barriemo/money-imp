<?php

namespace App\Domains\BusinessBrain\Evidence;

use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;

class InvoiceTruthEvidenceService
{
    public function forInvoice(
        AccountingInvoice $invoice
    ): InvoiceTruthEvidence {
        $allocations =
            PaymentAllocation::query()
                ->where(
                    'accounting_invoice_id',
                    $invoice->id
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

        $accountingSaysPaid =
            $invoice->status === 'paid'
            || (float) $invoice->outstanding_amount === 0.0;

        $approvedPaymentValue =
            (float) $approved
                ->sum(
                    'amount'
                );

        $hasBankEvidence =
            $approved->isNotEmpty();

        $hasEvidenceConflict =
            $accountingSaysPaid
            && ! $hasBankEvidence;

        $reasons =
            collect();

        if ($accountingSaysPaid) {
            $reasons->push(
                'Accounting source reports the invoice as paid.'
            );
        }

        if ($hasBankEvidence) {
            $reasons->push(
                sprintf(
                    'Approved payment evidence totals £%s.',
                    number_format(
                        $approvedPaymentValue,
                        2
                    )
                )
            );
        }

        if ($hasEvidenceConflict) {
            $reasons->push(
                'No approved bank reconciliation evidence supports the paid accounting status.'
            );
        }

        return new InvoiceTruthEvidence(
            invoiceId: (string) $invoice->id,

            invoiceNumber: $invoice->invoice_number,

            accountingStatus: $invoice->status,

            grossAmount: (float) $invoice->gross_amount,

            paidAmount: (float) $invoice->paid_amount,

            outstandingAmount: (float) $invoice->outstanding_amount,

            paymentAllocationCount: $allocations->count(),

            approvedPaymentAllocationCount: $approved->count(),

            approvedPaymentValue: $approvedPaymentValue,

            accountingSaysPaid: $accountingSaysPaid,

            hasBankEvidence: $hasBankEvidence,

            hasEvidenceConflict: $hasEvidenceConflict,

            confidence: $this->confidence(
                accountingSaysPaid: $accountingSaysPaid,
                hasBankEvidence: $hasBankEvidence,
                approvedPaymentValue: $approvedPaymentValue,
                grossAmount: (float) $invoice->gross_amount
            ),

            reasons: $reasons
                ->values()
                ->all()
        );
    }

    private function confidence(
        bool $accountingSaysPaid,
        bool $hasBankEvidence,
        float $approvedPaymentValue,
        float $grossAmount
    ): int {
        if (
            $accountingSaysPaid
            && $hasBankEvidence
            && $approvedPaymentValue >= $grossAmount
        ) {
            return 100;
        }

        if (
            $accountingSaysPaid
            && $hasBankEvidence
        ) {
            return 85;
        }

        if (
            $accountingSaysPaid
            && ! $hasBankEvidence
        ) {
            return 60;
        }

        return 90;
    }
}
