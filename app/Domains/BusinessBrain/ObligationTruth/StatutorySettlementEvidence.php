<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

final class StatutorySettlementEvidence
{
    public function __construct(
        public readonly array $categories,
        public readonly float $totalObservedAmount,
        public readonly bool $paymentEvidenceExists,
    ) {}

    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'total_observed_amount' => $this->totalObservedAmount,
            'payment_evidence_exists' => $this->paymentEvidenceExists,
        ];
    }
}
