<?php

namespace App\Domains\BusinessBrain\BankTruth;

use App\Models\BankTransaction;
use Illuminate\Support\Collection;

class BankTransactionDeduplicationService
{
    public function current(): Collection
    {
        return BankTransaction::query()
            ->where('transaction_type', 'customer_payment')
            ->where('amount', '>', 0)
            ->get()
            ->groupBy(
                fn (BankTransaction $transaction) => implode('|', [
                    $transaction->bank_account_id,
                    $transaction->client_id,
                    $transaction->transaction_date?->toDateString(),
                    number_format(
                        (float) $transaction->amount,
                        2,
                        '.',
                        ''
                    ),
                ])
            )
            ->map(
                fn (Collection $transactions) => $this->canonical($transactions)
            )
            ->values();
    }

    private function canonical(Collection $transactions): CanonicalBankTransaction
    {
        $primary = $transactions
            ->sortByDesc(
                fn (BankTransaction $transaction) => $this->sourceConfidence($transaction)
            )
            ->first();

        return new CanonicalBankTransaction(
            id: (string) $primary->id,
            date: $primary->transaction_date->toDateString(),
            amount: (float) $primary->amount,
            clientId: $primary->client_id,
            description: $primary->description,
            bankAccountId: $primary->bank_account_id,
            evidence: $transactions,

            resolution: $transactions->count() > 1
                ? 'duplicate_evidence'
                : 'single_source',

            confidence: $transactions->count() > 1
                ? 100
                : 80,
        );
    }

    private function sourceConfidence(
        BankTransaction $transaction
    ): int {
        return match ($transaction->source_type) {
            'file_import' => 100,
            'freeagent' => 70,
            default => 50,
        };
    }
}
