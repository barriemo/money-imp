<?php

namespace App\Domains\CommercialTruth\DTO;

final class CommercialAgreementCandidate
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $serviceType,
        public readonly string $serviceKey,
        public readonly string $cadence,
        public readonly float $observedValue,
        public readonly float $monthlyEquivalent,
        public readonly int $confidence,
        public readonly string $source,
        public readonly string $reason,
        public readonly array $evidence,
    ) {}
}
