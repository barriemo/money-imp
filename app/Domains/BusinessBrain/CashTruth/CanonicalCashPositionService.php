<?php

namespace App\Domains\BusinessBrain\CashTruth;

use App\Domains\BusinessBrain\BankTruth\BankTransactionDeduplicationService;
use App\Models\PaymentAllocation;

class CanonicalCashPositionService
{
    public function __construct(
        private BankTransactionDeduplicationService $transactions
    ) {}

    public function current(): CanonicalCashPosition
    {
        $movements =
            $this->transactions
                ->current()
                ->filter(
                    fn ($transaction) => $transaction->amount > 0
                )
                ->map(
                    fn ($transaction) => new CanonicalCashMovement(
                        id: $transaction->id,
                        date: $transaction->date,
                        amount: $transaction->amount,
                        clientId: $transaction->clientId,
                        description: $transaction->description,
                        allocated: $this->allocated($transaction->id),
                        evidenceCount: $transaction->evidence->count(),
                        confidence: $transaction->confidence,
                    )
                );

        return new CanonicalCashPosition(
            totalIncomingCash: round(
                $movements->sum('amount'),
                2
            ),

            allocatedCustomerCash: round(
                $movements
                    ->filter(
                        fn ($movement) => $movement->allocated
                    )
                    ->sum('amount'),
                2
            ),

            unallocatedCash: round(
                $movements
                    ->filter(
                        fn ($movement) => ! $movement->allocated
                    )
                    ->sum('amount'),
                2
            ),

            movementCount: $movements->count(),

            confidence: $movements
                ->avg('confidence') ?? 0
        );
    }

    private function allocated(
        string $transactionId
    ): bool {
        return PaymentAllocation::query()
            ->where(
                'bank_transaction_id',
                $transactionId
            )
            ->where(
                'status',
                'approved'
            )
            ->exists();
    }
}
