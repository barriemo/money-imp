<?php

namespace App\Domains\Accounting\FreeAgent\Evidence;

final class FreeAgentExpenseEvidence
{
    public function __construct(
        public readonly string $expenseId,
        public readonly string $description,
        public readonly string $date,
        public readonly float $grossAmount,
        public readonly float $vatAmount,
        public readonly float $vatRate,
        public readonly ?string $category,
        public readonly ?string $user,
        public readonly int $confidence = 90,
    ) {}
}
