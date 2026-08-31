<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

final class ObligationReconciliation
{
    public function __construct(
        public readonly array $categories,
        public readonly float $reportedTotal,
        public readonly float $paymentsObserved,
        public readonly float $unresolvedDifference,
        public readonly string $confidence,
    ) {}

    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'reported_total' => $this->reportedTotal,
            'payments_observed' => $this->paymentsObserved,
            'unresolved_difference' => $this->unresolvedDifference,
            'confidence' => $this->confidence,
        ];
    }
}
