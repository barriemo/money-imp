<?php

namespace App\Domains\BusinessBrain\CashTruth;

class CashTruth
{
    public function __construct(
        public int $accountCount,

        public int $verifiedAccountCount,

        public int $freshAccountCount,

        public int $staleAccountCount,

        public int $unverifiedAccountCount,

        public float $verifiedCash,

        public float $reportedAccountingBalance,

        public float $reportedUnverifiedCardDebt,

        public float $creditCardDebt,

        public float $knownLiabilities,

        public float $knownNetPosition,

        public ?float $safeAvailableCash,

        public float $ledgerReceivables,

        public float $paymentsWaitingAllocation,

        public int $bankVerificationConfidence,

        public int $bankFreshnessConfidence,

        public int $liabilityConfidence,

        public int $receivableConfidence,

        public int $cashConfidence,

        public ?string $oldestBalanceAt,

        public ?string $newestBalanceAt
    ) {}
}
