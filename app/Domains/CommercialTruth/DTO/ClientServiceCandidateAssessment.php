<?php

namespace App\Domains\CommercialTruth\DTO;

final class ClientServiceCandidateAssessment
{
    public function __construct(
        public readonly ClientServiceCandidate $candidate,
        public readonly string $asOfDate,
        public readonly ?int $daysSinceLastObservation,
        public readonly string $freshness,
        public readonly bool $cadenceEstablished,
        public readonly bool $recurringEvidence,
        public readonly ?float $currentMonthlyEquivalent,
        public readonly string $promotionReadiness,
        public readonly array $reasons,
    ) {}
}
