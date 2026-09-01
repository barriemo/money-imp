<?php

namespace App\Domains\CommercialTruth\DTO;

final class CanonicalServiceObservedBilling
{
    public function __construct(
        public readonly string $clientServiceId,
        public readonly string $clientId,
        public readonly string $clientName,
        public readonly string $serviceName,
        public readonly string $serviceStatus,
        public readonly int $evidenceCount,
        public readonly array $invoiceItemIds,
        public readonly float $signedObservedNet,
        public readonly float $latestObservedUnitPrice,
        public readonly ?string $firstObservedOn,
        public readonly ?string $lastObservedOn,
        public readonly string $cadence,
        public readonly float $monthlyEquivalent,
        public readonly int $cadenceConfidence,
        public readonly ?int $daysSinceLastObservation,
        public readonly string $freshness,
        public readonly bool $recurringEvidence,
        public readonly ?float $currentMonthlyEquivalent,
    ) {}
}
