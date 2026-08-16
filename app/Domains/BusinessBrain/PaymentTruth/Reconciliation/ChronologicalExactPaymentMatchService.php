<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Reconciliation;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidence;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;

class ChronologicalExactPaymentMatchService
{
    public function __construct(
        private CanonicalPaymentEvidenceService $payments
    ) {}

    public function generate(): array
    {
        $stats = [
            'considered' => 0,
            'created' => 0,
            'value' => 0.0,
            'still_ambiguous' => 0,
        ];

        foreach ($this->payments->customerPayments() as $payment) {
            if ($this->hasAllocation($payment)) {
                continue;
            }

            $candidates =
                $this->candidates(
                    $payment
                );

            if ($candidates->count() <= 1) {
                continue;
            }

            $stats['considered']++;

            $remaining =
                $candidates
                    ->reject(
                        fn (AccountingInvoice $invoice) => $this->invoiceAlreadyHasPaymentEvidence(
                            $invoice->id
                        )
                    )
                    ->values();

            if ($remaining->count() !== 1) {
                $stats['still_ambiguous']++;

                continue;
            }

            $invoice =
                $remaining->first();

            PaymentAllocation::create([
                'bank_transaction_id' => $payment->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => round(
                    $payment->amount,
                    2
                ),

                'status' => 'suggested',

                'confidence' => 95,

                'match_method' => 'canonical_chronological_exact_amount',

                'reason' => 'Multiple same-value invoices existed, but exactly one remains without existing payment evidence.',

                'metadata' => [
                    'canonical_evidence_ids' => $payment->evidenceIds,
                    'candidate_count' => $candidates->count(),
                    'remaining_candidate_count' => 1,
                ],
            ]);

            $stats['created']++;

            $stats['value'] =
                round(
                    $stats['value']
                    + $payment->amount,
                    2
                );
        }

        return $stats;
    }

    private function hasAllocation(
        CanonicalPaymentEvidence $payment
    ): bool {
        return PaymentAllocation::query()
            ->whereIn(
                'bank_transaction_id',
                $payment->evidenceIds
            )
            ->exists();
    }

    private function candidates(
        CanonicalPaymentEvidence $payment
    ) {
        if (! $payment->clientId) {
            return collect();
        }

        return AccountingInvoice::query()
            ->where(
                'client_id',
                $payment->clientId
            )
            ->where(
                'gross_amount',
                '>',
                0
            )
            ->whereRaw(
                'ABS(gross_amount - ?) < 0.01',
                [
                    $payment->amount,
                ]
            )
            ->where(
                function ($query) use ($payment): void {
                    $query
                        ->whereNull(
                            'invoice_date'
                        )
                        ->orWhereDate(
                            'invoice_date',
                            '<=',
                            $payment->date
                        );
                }
            )
            ->orderBy(
                'invoice_date'
            )
            ->get();
    }

    private function invoiceAlreadyHasPaymentEvidence(
        string $invoiceId
    ): bool {
        return PaymentAllocation::query()
            ->where(
                'accounting_invoice_id',
                $invoiceId
            )
            ->whereIn(
                'status',
                [
                    'approved',
                    'imported',
                    'suggested',
                ]
            )
            ->exists();
    }
}
