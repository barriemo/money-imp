<?php

namespace App\Domains\BusinessBrain\BankTruth;

class BankEvidenceCoverage
{
    public function __construct(
        public readonly ?string $latestTransactionDate,
        public readonly ?string $latestBalanceAt,
        public readonly ?int $daysSinceLatestTransaction,
        public readonly ?int $daysSinceLatestBalance,
        public readonly bool $transactionEvidenceCurrent,
        public readonly bool $balanceEvidenceCurrent,
    ) {}

    public function canInferPaymentAbsence(): bool
    {
        return $this->transactionEvidenceCurrent;
    }
}
