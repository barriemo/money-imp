<?php

namespace App\Domains\CommercialTruth\DTO;

final class ClientServiceCandidate
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientName,
        public readonly string $serviceType,
        public readonly ?string $serviceHint,
        public readonly string $fingerprint,
        public readonly string $commercialTreatment,
        public readonly int $evidenceCount,
        public readonly array $invoiceItemIds,
        public readonly float $signedObservedNet,
        public readonly float $positiveObservedNet,
        public readonly float $negativeObservedNet,
        public readonly float $latestObservedUnitPrice,
        public readonly ?string $firstObservedOn,
        public readonly ?string $lastObservedOn,
        public readonly string $cadence,
        public readonly float $monthlyEquivalent,
        public readonly int $classificationConfidence,
        public readonly int $cadenceConfidence,
        public readonly array $commercialComponents = [],
    ) {}

    public function isCompositeCandidate(): bool
    {
        return $this->commercialTreatment
            === 'composite_candidate';
    }

    public function isServiceCandidate(): bool
    {
        return in_array(
            $this->commercialTreatment,
            [
                'service_candidate',
                'managed_service_candidate',
            ],
            true
        );
    }
}
