<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

use App\Domains\BusinessBrain\CreditTruth\CreditTruthService;
use App\Models\BankTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CreditObligationReconciliationService
{
    public function __construct(
        private CreditTruthService $creditTruth
    ) {}

    public function current(): Collection
    {
        return $this->creditTruth
            ->current()
            ->facilities
            ->filter(
                fn ($facility) => $facility->minimumPayment !== null
                    && $facility->paymentDueAt !== null
            )
            ->map(
                fn ($facility) => $this->reconcile(
                    $facility
                )
            )
            ->values();
    }

    private function reconcile(
        $facility
    ): CreditObligation {
        $amountDue =
            round(
                (float) $facility->minimumPayment,
                2
            );

        $dueAt =
            CarbonImmutable::parse(
                $facility->paymentDueAt
            );

        $candidate =
            BankTransaction::query()
                ->where(
                    'amount',
                    -$amountDue
                )
                ->whereBetween(
                    'transaction_date',
                    [
                        $dueAt
                            ->subDays(3)
                            ->startOfDay(),

                        $dueAt
                            ->addDays(3)
                            ->endOfDay(),
                    ]
                )
                ->get()
                ->filter(
                    function (BankTransaction $transaction) use ($facility): bool {
                        $description =
                            mb_strtolower(
                                $transaction->description
                                ?? ''
                            );

                        return match ($facility->provider) {
                            'capital_on_tap' => str_contains(
                                $description,
                                'capital on tap'
                            ),

                            default => false,
                        };
                    }
                )
                ->sortBy(
                    fn (BankTransaction $transaction) => abs(
                        $transaction
                            ->transaction_date
                            ->diffInSeconds(
                                $dueAt
                            )
                    )
                )
                ->first();

        if (! $candidate) {
            return new CreditObligation(
                facilityId: $facility->id,

                facility: $facility->name,

                amountDue: $amountDue,

                dueAt: $dueAt->toDateString(),

                status: 'unmatched',

                matchedPayment: null,

                matchedAt: null,

                confidence: 100
            );
        }

        return new CreditObligation(
            facilityId: $facility->id,

            facility: $facility->name,

            amountDue: $amountDue,

            dueAt: $dueAt->toDateString(),

            status: 'satisfied',

            matchedPayment: abs(
                round(
                    (float) $candidate->amount,
                    2
                )
            ),

            matchedAt: $candidate
                ->transaction_date
                ->toDateString(),

            confidence: 100
        );
    }
}
