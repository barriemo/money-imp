<?php

namespace App\Domains\CommercialTruth\DTO;

final class CommercialAgreementCoverageReviewCandidate
{
    public function __construct(
        public readonly string $clientServiceId,
        public readonly string $clientId,
        public readonly string $clientName,
        public readonly string $serviceName,
        public readonly string $serviceType,
        public readonly string $coverageState,
        public readonly int $priority,
        public readonly string $priorityReason,
        public readonly ?string $coverageReviewId,
        public readonly ?string $coverageOutcome,
        public readonly ?string $coverageEffectiveFrom,
        public readonly string $observedBillingState,
        public readonly int $observedEvidenceCount,
        public readonly ?string $observedCadence,
        public readonly ?string $observedFreshness,
        public readonly ?string $firstObservedOn,
        public readonly ?string $lastObservedOn,
        public readonly ?float $observedCurrentMonthlyEquivalent,
        public readonly ?string $currentAgreementId,
        public readonly ?string $currentAgreementStatus,
        public readonly ?string $currentAgreementCadence,
        public readonly ?int $currentAgreementAmountPence,
        public readonly ?float $currentAgreementMonthlyEquivalent,
        public readonly array $availableDecisions,
    ) {}
}
