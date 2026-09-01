<?php

namespace App\Domains\CommercialTruth\DTO;

final class ClientServiceAttributionCandidate
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientName,
        public readonly string $candidateFingerprint,
        public readonly string $serviceType,
        public readonly ?string $serviceHint,
        public readonly array $invoiceItemIds,
        public readonly int $evidenceCount,
        public readonly float $signedObservedNet,
        public readonly ?string $firstObservedOn,
        public readonly ?string $lastObservedOn,
        public readonly string $matchStatus,
        public readonly ?string $clientServiceId,
        public readonly ?string $clientServiceName,
        public readonly array $candidateClientServiceIds,
        public readonly array $supportingReconciliationIds,
    ) {}

    public function isReadyForReview(): bool
    {
        return $this->matchStatus === 'matched';
    }
}
