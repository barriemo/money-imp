<?php

namespace App\Domains\CommercialTruth\DTO;

final class CurrentCommercialPosition
{
    public function __construct(
        public readonly string $asOfDate,
        public readonly int $serviceCandidateCount,
        public readonly int $recurringCandidateCount,
        public readonly int $currentRecurringCandidateCount,
        public readonly float $supportedCurrentMonthlyEquivalent,
        public readonly int $recentlyObservedRecurringCandidateCount,
        public readonly float $recentlyObservedMonthlyEquivalent,
        public readonly int $staleRecurringCandidateCount,
        public readonly float $staleMonthlyEquivalent,
        public readonly int $historicalRecurringCandidateCount,
        public readonly float $historicalMonthlyEquivalent,
        public readonly int $readyForReviewCount,
        public readonly int $needsMoreEvidenceCount,
        public readonly int $sourceEvidenceItemCount,
        public readonly int $currentEvidenceItemCount,
        public readonly array $byServiceType,
        public readonly array $byClient,
        public readonly string $evidenceStatus,
        public readonly array $caveats,
        public readonly array $provenance,
    ) {}

    public function toArray(): array
    {
        return [
            'as_of_date' => $this->asOfDate,

            'service_candidate_count' => $this->serviceCandidateCount,

            'recurring_candidate_count' => $this->recurringCandidateCount,

            'current_recurring_candidate_count' => $this->currentRecurringCandidateCount,

            'supported_current_monthly_equivalent' => $this->supportedCurrentMonthlyEquivalent,

            'recently_observed_recurring_candidate_count' => $this->recentlyObservedRecurringCandidateCount,

            'recently_observed_monthly_equivalent' => $this->recentlyObservedMonthlyEquivalent,

            'stale_recurring_candidate_count' => $this->staleRecurringCandidateCount,

            'stale_monthly_equivalent' => $this->staleMonthlyEquivalent,

            'historical_recurring_candidate_count' => $this->historicalRecurringCandidateCount,

            'historical_monthly_equivalent' => $this->historicalMonthlyEquivalent,

            'ready_for_review_count' => $this->readyForReviewCount,

            'needs_more_evidence_count' => $this->needsMoreEvidenceCount,

            'source_evidence_item_count' => $this->sourceEvidenceItemCount,

            'current_evidence_item_count' => $this->currentEvidenceItemCount,

            'by_service_type' => $this->byServiceType,

            'by_client' => $this->byClient,

            'evidence_status' => $this->evidenceStatus,

            'caveats' => $this->caveats,

            'provenance' => $this->provenance,
        ];
    }
}
