<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Analysis;

use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidence;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\AccountingInvoice;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

class UnmatchedPaymentAnalysisService
{
    public function __construct(
        private CanonicalPaymentEvidenceService $payments
    ) {}

    public function current(): UnmatchedPaymentAnalysis
    {
        $unmatched =
            $this->payments
                ->customerPayments()
                ->filter(
                    fn (CanonicalPaymentEvidence $payment) => ! $this->hasAllocation(
                        $payment->evidenceIds
                    )
                );

        $classified =
            $unmatched->map(
                fn (CanonicalPaymentEvidence $payment) => [
                    'payment' => $payment,

                    'state' => $this->exactMatchState(
                        $payment
                    ),
                ]
            );

        $unique =
            $classified->where(
                'state',
                'unique_exact_match'
            );

        $ambiguous =
            $classified->where(
                'state',
                'ambiguous_exact_match'
            );

        $none =
            $classified->where(
                'state',
                'no_exact_match'
            );

        return new UnmatchedPaymentAnalysis(
            paymentCount: $classified->count(),

            paymentValue: $this->value(
                $classified
            ),

            uniqueExactMatchCount: $unique->count(),

            uniqueExactMatchValue: $this->value(
                $unique
            ),

            ambiguousExactMatchCount: $ambiguous->count(),

            ambiguousExactMatchValue: $this->value(
                $ambiguous
            ),

            noExactMatchCount: $none->count(),

            noExactMatchValue: $this->value(
                $none
            )
        );
    }

    private function hasAllocation(
        array $transactionIds
    ): bool {
        return PaymentAllocation::query()
            ->whereIn(
                'bank_transaction_id',
                $transactionIds
            )
            ->exists();
    }

    private function exactMatchState(
        CanonicalPaymentEvidence $payment
    ): string {
        if (! $payment->clientId) {
            return 'no_exact_match';
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
                ->count();

        return match (true) {
            $matches === 1 => 'unique_exact_match',

            $matches > 1 => 'ambiguous_exact_match',

            default => 'no_exact_match',
        };
    }

    private function value(
        Collection $classified
    ): float {
        return round(
            (float) $classified->sum(
                fn (array $item) => $item[
                    'payment'
                ]->amount
            ),
            2
        );
    }
}
