<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Domains\CommercialTruth\DTO\CurrentCommercialPosition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CurrentCommercialPositionService
{
    public function __construct(
        private readonly ClientServiceCandidateAssessmentService $assessments,
    ) {}

    public function position(
        ?CarbonImmutable $asOf = null
    ): CurrentCommercialPosition {
        $asOf ??= CarbonImmutable::today();

        $assessments = $this->assessments
            ->all($asOf);

        $services = $assessments
            ->filter(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment
                    ->candidate
                    ->isServiceCandidate()
            )
            ->values();

        $recurring = $services
            ->filter(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment->recurringEvidence
            )
            ->values();

        $current = $this->withFreshness(
            $recurring,
            'current'
        );

        $recentlyObserved = $this->withFreshness(
            $recurring,
            'recently_observed'
        );

        $stale = $this->withFreshness(
            $recurring,
            'stale'
        );

        $historical = $this->withFreshness(
            $recurring,
            'historical'
        );

        return new CurrentCommercialPosition(
            asOfDate: $asOf->toDateString(),

            serviceCandidateCount: $services->count(),

            recurringCandidateCount: $recurring->count(),

            currentRecurringCandidateCount: $current->count(),

            supportedCurrentMonthlyEquivalent: $this->supportedCurrentValue(
                $current
            ),

            recentlyObservedRecurringCandidateCount: $recentlyObserved->count(),

            recentlyObservedMonthlyEquivalent: $this->observedMonthlyEquivalent(
                $recentlyObserved
            ),

            staleRecurringCandidateCount: $stale->count(),

            staleMonthlyEquivalent: $this->observedMonthlyEquivalent(
                $stale
            ),

            historicalRecurringCandidateCount: $historical->count(),

            historicalMonthlyEquivalent: $this->observedMonthlyEquivalent(
                $historical
            ),

            readyForReviewCount: $services
                ->where(
                    'promotionReadiness',
                    'ready_for_review'
                )
                ->count(),

            needsMoreEvidenceCount: $services
                ->where(
                    'promotionReadiness',
                    'needs_more_evidence'
                )
                ->count(),

            sourceEvidenceItemCount: (int) $services->sum(
                fn (
                    ClientServiceCandidateAssessment $assessment
                ) => $assessment
                    ->candidate
                    ->evidenceCount
            ),

            currentEvidenceItemCount: (int) $current->sum(
                fn (
                    ClientServiceCandidateAssessment $assessment
                ) => $assessment
                    ->candidate
                    ->evidenceCount
            ),

            byServiceType: $this->byServiceType(
                $current
            ),

            byClient: $this->byClient(
                $current
            ),

            evidenceStatus: 'invoice_history_supported_not_reconciled',

            caveats: [
                'Invoice-history evidence is not canonical ClientService truth.',
                'Supported current monthly equivalent is not MRR, contracted revenue, cash, or margin.',
                'Recently observed, stale and historical recurring evidence is excluded from supported current value.',
                'Current candidates remain subject to human reconciliation before canonical promotion.',
            ],

            provenance: [
                'source' => 'accounting_invoice_items',

                'classification' => 'CommercialServiceFingerprint',

                'cadence' => 'BillingCadenceEngine',

                'candidate_aggregation' => 'ClientServiceCandidateService',

                'freshness_assessment' => 'ClientServiceCandidateAssessmentService',
            ],
        );
    }

    private function withFreshness(
        Collection $assessments,
        string $freshness
    ): Collection {
        return $assessments
            ->filter(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment->freshness
                        === $freshness
            )
            ->values();
    }

    private function supportedCurrentValue(
        Collection $assessments
    ): float {
        return round(
            (float) $assessments->sum(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment
                    ->currentMonthlyEquivalent
                        ?? 0
            ),
            2
        );
    }

    private function observedMonthlyEquivalent(
        Collection $assessments
    ): float {
        return round(
            (float) $assessments->sum(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment
                    ->candidate
                    ->monthlyEquivalent
            ),
            2
        );
    }

    private function byServiceType(
        Collection $current
    ): array {
        return $current
            ->groupBy(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment
                    ->candidate
                    ->serviceType
            )
            ->map(
                function (
                    Collection $rows,
                    string $serviceType
                ): array {
                    return [
                    'service_type' => $serviceType,

                    'candidate_count' => $rows->count(),

                    'client_count' => $rows
                        ->pluck(
                            'candidate.clientId'
                        )
                        ->unique()
                        ->count(),

                    'evidence_item_count' => (int) $rows->sum(
                        fn (
                            ClientServiceCandidateAssessment $row
                        ) => $row
                            ->candidate
                            ->evidenceCount
                    ),

                    'supported_current_monthly_equivalent' => $this->supportedCurrentValue(
                        $rows
                    ),
                    ];
                }
            )
            ->sortByDesc(
                'supported_current_monthly_equivalent'
            )
            ->values()
            ->all();
    }

    private function byClient(
        Collection $current
    ): array {
        return $current
            ->groupBy(
                fn (ClientServiceCandidateAssessment $assessment) => $assessment
                    ->candidate
                    ->clientId
            )
            ->map(
                function (Collection $rows): array {
                    $first = $rows->first();

                    return [
                    'client_id' => $first
                        ->candidate
                        ->clientId,

                    'client_name' => $first
                        ->candidate
                        ->clientName,

                    'service_count' => $rows->count(),

                    'evidence_item_count' => (int) $rows->sum(
                        fn (
                            ClientServiceCandidateAssessment $row
                        ) => $row
                            ->candidate
                            ->evidenceCount
                    ),

                    'supported_current_monthly_equivalent' => $this->supportedCurrentValue(
                        $rows
                    ),
                    ];
                }
            )
            ->sortByDesc(
                'supported_current_monthly_equivalent'
            )
            ->values()
            ->all();
    }
}
