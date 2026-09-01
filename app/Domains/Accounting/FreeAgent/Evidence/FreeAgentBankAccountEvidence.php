<?php

namespace App\Domains\Accounting\FreeAgent\Evidence;

final class FreeAgentBankAccountEvidence
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $name,
        public readonly string $type,
        public readonly float $balance,
        public readonly int $transactionCount,
        public readonly int $unexplainedTransactionCount,
        public readonly int $markedForReviewCount,
        public readonly int $manualTransactionCount,
        public readonly array $categoryGroups,
        public readonly ?string $latestActivityDate,
        public readonly ?bool $bankFeedEnabled,
        public readonly int $confidence = 90,
    ) {}
}
