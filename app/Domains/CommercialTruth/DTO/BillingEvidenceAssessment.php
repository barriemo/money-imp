<?php

namespace App\Domains\CommercialTruth\DTO;

final class BillingEvidenceAssessment
{
    public function __construct(
        public readonly ?int $daysSinceLastObservation,
        public readonly string $freshness,
        public readonly bool $cadenceEstablished,
        public readonly bool $recurringEvidence,
        public readonly ?float $currentMonthlyEquivalent,
    ) {}
}
