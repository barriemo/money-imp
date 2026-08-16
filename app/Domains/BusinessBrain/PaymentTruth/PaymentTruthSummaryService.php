<?php

namespace App\Domains\BusinessBrain\PaymentTruth;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;

class PaymentTruthSummaryService
{
    public function __construct(
        private InvoicePaymentTruthService $payments,

        private CanonicalPaymentEvidenceService $canonicalPayments
    ) {}

    public function current(): PaymentTruthSummary
    {
        $truth =
            $this->payments
                ->current();

        $invoiceCount =
            $truth->count();

        $totalInvoiced =
            $this->money(
                $truth->sum(
                    'grossAmount'
                )
            );

        $bankConfirmedReceived =
            $this->money(
                $truth->sum(
                    'bankConfirmedPaid'
                )
            );

        $suggestedReceived =
            $this->money(
                $truth->sum(
                    'suggestedPaid'
                )
            );

        $provenOutstanding =
            $this->money(
                $truth->sum(
                    'provenOutstanding'
                )
            );

        $accountingReportedPaid =
            $this->money(
                $truth->sum(
                    'accountingPaid'
                )
            );

        $accountingReportedOutstanding =
            $this->money(
                $truth->sum(
                    'accountingOutstanding'
                )
            );

        $conflicts =
            $truth->where(
                'accountingConflict',
                true
            );

        $accountingConflictValue =
            $this->money(
                $conflicts->sum(
                    'grossAmount'
                )
            );

        $unallocatedIncomingCash =
            $this->money(
                $this->canonicalPayments
                    ->unallocatedCustomerPayments()
                    ->sum(
                        fn ($payment) => $payment->amount
                    )
            );

        return new PaymentTruthSummary(
            invoiceCount: $invoiceCount,

            totalInvoiced: $totalInvoiced,

            bankConfirmedReceived: $bankConfirmedReceived,

            suggestedReceived: $suggestedReceived,

            provenOutstanding: $provenOutstanding,

            accountingReportedPaid: $accountingReportedPaid,

            accountingReportedOutstanding: $accountingReportedOutstanding,

            accountingConflictValue: $accountingConflictValue,

            paidInvoiceCount: $truth
                ->where(
                    'status',
                    'paid'
                )
                ->count(),

            partPaidInvoiceCount: $truth
                ->where(
                    'status',
                    'part_paid'
                )
                ->count(),

            ambiguousInvoiceCount: $truth
                ->where(
                    'status',
                    'ambiguous'
                )
                ->count(),

            unpaidInvoiceCount: $truth
                ->where(
                    'status',
                    'unpaid'
                )
                ->count(),

            accountingConflictCount: $conflicts
                ->count(),

            unallocatedIncomingCash: $unallocatedIncomingCash,

            confidence: $this->confidence(
                invoiceCount: $invoiceCount,

                conflictCount: $conflicts
                    ->count(),

                ambiguousCount: $truth
                    ->where(
                        'status',
                        'ambiguous'
                    )
                    ->count()
            )
        );
    }

    private function confidence(
        int $invoiceCount,
        int $conflictCount,
        int $ambiguousCount
    ): int {
        if ($invoiceCount === 0) {
            return 100;
        }

        $resolved =
            max(
                0,
                $invoiceCount
                - $conflictCount
                - $ambiguousCount
            );

        return (int) round(
            (
                $resolved
                / $invoiceCount
            ) * 100
        );
    }

    private function money(
        float|int|string $value
    ): float {
        return round(
            (float) $value,
            2
        );
    }
}
