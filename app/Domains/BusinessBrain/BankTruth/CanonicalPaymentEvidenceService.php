<?php

namespace App\Domains\BusinessBrain\BankTruth;

use App\Models\PaymentAllocation;
use Illuminate\Support\Collection;

class CanonicalPaymentEvidenceService
{
    public function __construct(
        private BankTransactionDeduplicationService $deduplication
    ) {}

    /**
     * @return Collection<int, CanonicalPaymentEvidence>
     */
    public function customerPayments()
    {
        return $this->deduplication
            ->current()
            ->filter(
                fn (CanonicalBankTransaction $transaction) => $transaction->amount > 0
            )
            ->map(
                fn (CanonicalBankTransaction $transaction) => new CanonicalPaymentEvidence(
                    id: $transaction->id,

                    date: $transaction->date,

                    amount: $transaction->amount,

                    clientId: $transaction->clientId,

                    description: $transaction->description,

                    confidence: $transaction->confidence,

                    evidenceCount: $transaction
                        ->evidence
                        ->count(),

                    evidenceIds: $transaction
                        ->evidence
                        ->pluck('id')
                        ->map(
                            fn ($id) => (string) $id
                        )
                        ->values()
                        ->all()
                )
            )
            ->values();
    }

    /**
     * @return Collection<int, CanonicalPaymentEvidence>
     */
    public function unallocatedCustomerPayments()
    {
        return $this->customerPayments()
            ->filter(
                fn (CanonicalPaymentEvidence $payment) => ! $this->hasApprovedAllocation(
                    $payment->evidenceIds
                )
            )
            ->values();
    }

    private function hasApprovedAllocation(
        array $transactionIds
    ): bool {
        return PaymentAllocation::query()
            ->whereIn(
                'bank_transaction_id',
                $transactionIds
            )
            ->where(
                'status',
                'approved'
            )
            ->exists();
    }
}
