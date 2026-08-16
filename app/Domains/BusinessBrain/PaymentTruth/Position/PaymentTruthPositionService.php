<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Position;

use App\Domains\BusinessBrain\BankTruth\BankTransactionDeduplicationService;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidence;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

class PaymentTruthPositionService
{
    public function __construct(
        private CanonicalPaymentEvidenceService $payments,

        private BankTransactionDeduplicationService $deduplication
    ) {}

    public function current(): PaymentTruthPosition
    {
        $payments =
            $this->payments
                ->customerPayments();

        $classified =
            $payments->map(
                fn (CanonicalPaymentEvidence $payment) => [
                    'payment' => $payment,

                    'state' => $this->state(
                        $payment->evidenceIds
                    ),
                ]
            );

        $allocated =
            $classified->where(
                'state',
                'allocated'
            );

        $suggested =
            $classified->where(
                'state',
                'suggested'
            );

        $unmatched =
            $classified->where(
                'state',
                'unmatched'
            );

        $duplicates =
            $this->deduplication
                ->current()
                ->filter(
                    fn ($transaction) => $transaction
                        ->evidence
                        ->count() > 1
                );

        return new PaymentTruthPosition(
            canonicalReceived: $this->value(
                $classified
            ),

            allocatedReceived: $this->value(
                $allocated
            ),

            suggestedReceived: $this->value(
                $suggested
            ),

            unmatchedReceived: $this->value(
                $unmatched
            ),

            paymentCount: $payments->count(),

            allocatedPaymentCount: $allocated->count(),

            suggestedPaymentCount: $suggested->count(),

            unmatchedPaymentCount: $unmatched->count(),

            duplicateEvidenceGroups: $duplicates
                ->count(),

            duplicateEvidenceValue: round(
                (float) $duplicates
                    ->sum(
                        'amount'
                    ),
                2
            ),

            confidence: $this->confidence(
                $classified
            )
        );
    }

    private function state(
        array $transactionIds
    ): string {
        $statuses =
            PaymentAllocation::query()
                ->whereIn(
                    'bank_transaction_id',
                    $transactionIds
                )
                ->pluck(
                    'status'
                );

        if (
            $statuses->contains(
                fn ($status) => in_array(
                    $status,
                    [
                        'approved',
                        'imported',
                    ],
                    true
                )
            )
        ) {
            return 'allocated';
        }

        if (
            $statuses->contains(
                'suggested'
            )
        ) {
            return 'suggested';
        }

        return 'unmatched';
    }

    private function value(
        Collection $classified
    ): float {
        return round(
            (float) $classified
                ->sum(
                    fn (array $item) => $item[
                        'payment'
                    ]->amount
                ),
            2
        );
    }

    private function confidence(
        Collection $classified
    ): int {
        if ($classified->isEmpty()) {
            return 0;
        }

        $weighted =
            $classified->sum(
                function (array $item): int {
                    return match (
                        $item['state']
                    ) {
                        'allocated' => 100,
                        'suggested' => 75,
                        default => 50,
                    };
                }
            );

        return (int) round(
            $weighted
            / $classified->count()
        );
    }
}
