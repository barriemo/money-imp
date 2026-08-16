<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Reconciliation;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidence;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;

class UniqueExactPaymentMatchService
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
        ];

        foreach (
            $this->payments->customerPayments() as $payment
        ) {
            if ($this->hasAllocation($payment)) {
                continue;
            }

            $stats['considered']++;

            $invoice =
                $this->uniqueInvoice(
                    $payment
                );

            if (! $invoice) {
                continue;
            }

            PaymentAllocation::create([
                'bank_transaction_id' => $payment->id,

                'accounting_invoice_id' => $invoice->id,

                'amount' => round(
                    $payment->amount,
                    2
                ),

                'status' => 'suggested',

                'confidence' => 100,

                'match_method' => 'canonical_client_exact_amount',

                'reason' => 'Canonical customer payment has exactly one same-client invoice with the same gross amount issued on or before the payment date.',

                'metadata' => [
                    'canonical_evidence_ids' => $payment->evidenceIds,

                    'evidence_count' => $payment->evidenceCount,
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

    private function uniqueInvoice(
        CanonicalPaymentEvidence $payment
    ): ?AccountingInvoice {
        if (! $payment->clientId) {
            return null;
        }

        $matches =
            AccountingInvoice::query()
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
                ->get();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }
}
