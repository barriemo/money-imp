<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\CommercialAgreementTruthService;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Domains\CommercialTruth\DTO\CurrentCommercialPosition;
use App\Models\AccountingInvoiceItem;
use App\Models\BillingRule;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CurrentCommercialPositionService
{
    public function __construct(
        private readonly ClientServiceCandidateAssessmentService $assessments,
        private readonly ClientServiceReconciliationQueueService $reviewQueue,
        private readonly CanonicalServiceObservedBillingService $canonicalObservedBilling,
        private readonly ClientServiceAttributionReviewQueueService $attributionReviewQueue,
        private readonly CommercialAgreementTruthService $contractedTruth,
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

        $unreconciledServices =
            $this->withoutCanonicalAttribution(
                $services
            );

        $recurring = $unreconciledServices
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

        $canonicalObserved =
            $this->canonicalObservedBilling
                ->all($asOf)
                ->filter(
                    fn ($row) => $row->serviceStatus
                        === 'active'
                )
                ->values();

        $canonicalCurrent =
            $canonicalObserved
                ->filter(
                    fn ($row) => $row->currentMonthlyEquivalent
                        !== null
                )
                ->values();

        $canonicalCurrentObservedMonthlyEquivalent =
            round(
                (float) $canonicalCurrent
                    ->sum(
                        fn ($row) => $row->currentMonthlyEquivalent
                            ?? 0
                    ),
                2
            );

        $unreconciledCurrentMonthlyEquivalent =
            $this->supportedCurrentValue(
                $current
            );

        /*
         * This is deliberately a partitioned total.
         *
         * A new unattributed invoice attached to an already
         * canonical service does not change recurring value
         * until a human approves that attribution.
         */
        $totalObservedCurrentMonthlyEquivalent =
            round(
                $canonicalCurrentObservedMonthlyEquivalent
                + $unreconciledCurrentMonthlyEquivalent,
                2
            );

        $canonicalActiveServiceCount =
            ClientService::query()
                ->where(
                    'status',
                    'active'
                )
                ->count();

        $contracted =
            $this->contractedTruth
                ->summary();

        /*
         * BillingRule has a soft-delete column but currently
         * does not use the SoftDeletes trait, so explicitly
         * exclude deleted rows here.
         */
        $billingRuleCount =
            BillingRule::query()
                ->whereNull(
                    'deleted_at'
                )
                ->where(
                    'status',
                    'active'
                )
                ->count();

        $evidenceStatus =
            $this->evidenceStatus(
                canonicalCurrentCount: $canonicalCurrent->count(),
                unreconciledCurrentCount: $current->count(),
                canonicalActiveServiceCount: $canonicalActiveServiceCount
            );

        return new CurrentCommercialPosition(
            asOfDate: $asOf->toDateString(),

            serviceCandidateCount: $unreconciledServices->count(),

            recurringCandidateCount: $recurring->count(),

            currentRecurringCandidateCount: $current->count(),

            supportedCurrentMonthlyEquivalent: $totalObservedCurrentMonthlyEquivalent,

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

            readyForReviewCount: $this->reviewQueue
                ->ready($asOf)
                ->count(),

            needsMoreEvidenceCount: $unreconciledServices
                ->where(
                    'promotionReadiness',
                    'needs_more_evidence'
                )
                ->count(),

            sourceEvidenceItemCount: (int) $unreconciledServices->sum(
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

            evidenceStatus: $evidenceStatus,

            caveats: [
                'Observed current monthly equivalent is evidence-derived; it is not MRR, contracted revenue, cash, or margin.',
                'Canonical-service-backed observed billing uses only paid or overdue invoice evidence human-attributed either directly to a ClientService or through a current conserved composite allocation; draft, written-off, refunded, unknown and stale allocation evidence are excluded.',
                'Unattributed evidence on an existing canonical service is excluded from recurring value until attribution is human-approved.',
                'Recently observed, stale and historical unresolved recurring evidence is excluded from current observed value.',
                'Contracted recurring value remains unknown until persisted commercial agreement truth is explicitly confirmed; BillingRule is operational invoicing configuration and is not contractual evidence.',
            ],

            provenance: [
                'source' => 'accounting_invoice_items',

                'classification' => 'CommercialServiceFingerprint',

                'cadence' => 'BillingCadenceEngine',

                'candidate_aggregation' => 'ClientServiceCandidateService',

                'freshness_assessment' => 'ClientServiceCandidateAssessmentService',
                'canonical_observed_billing' => 'CanonicalServiceObservedBillingService',
                'canonical_billing_observations' => 'CanonicalBillingObservationService',
                'canonical_billing_status_policy' => 'CanonicalBillingEvidenceStatusPolicy',
                'composite_allocation_ledger' => 'CommercialEvidenceAllocationSet',
                'contracted_commercial_truth' => 'CommercialAgreementTruthService',
                'billing_rule_role' => 'operational_invoicing_configuration_only',
                'attribution_review' => 'ClientServiceAttributionReviewQueueService',
            ],
            canonicalActiveServiceCount: $canonicalActiveServiceCount,
            canonicalServicesWithObservedBillingCount: $canonicalObserved->count(),
            canonicalCurrentRecurringServiceCount: $canonicalCurrent->count(),
            canonicalCurrentObservedMonthlyEquivalent: $canonicalCurrentObservedMonthlyEquivalent,
            unreconciledCurrentRecurringCandidateCount: $current->count(),
            unreconciledCurrentMonthlyEquivalent: $unreconciledCurrentMonthlyEquivalent,
            attributionReviewReadyCount: $this->attributionReviewQueue
                ->ready()
                ->count(),
            billingRuleCount: $billingRuleCount,
            contractedMonthlyValue: $contracted[
                'contracted_monthly_value'
            ],
            contractedValueStatus: $contracted[
                'contracted_value_status'
            ],
        );
    }

    private function withoutCanonicalAttribution(
        Collection $assessments
    ): Collection {
        $invoiceItemIds =
            $assessments
                ->flatMap(
                    fn (
                        ClientServiceCandidateAssessment $assessment
                    ) => $assessment
                        ->candidate
                        ->invoiceItemIds
                )
                ->unique()
                ->values();

        if ($invoiceItemIds->isEmpty()) {
            return $assessments;
        }

        $attributedIds =
            AccountingInvoiceItem::query()
                ->whereIn(
                    'id',
                    $invoiceItemIds->all()
                )
                ->whereNotNull(
                    'client_service_id'
                )
                ->pluck('id')
                ->map(
                    fn ($id) => (string) $id
                )
                ->flip();

        return $assessments
            ->filter(
                function (
                    ClientServiceCandidateAssessment $assessment
                ) use (
                    $attributedIds
                ): bool {
                    foreach (
                        $assessment
                            ->candidate
                            ->invoiceItemIds as $invoiceItemId
                    ) {
                        if (
                            $attributedIds->has(
                                (string) $invoiceItemId
                            )
                        ) {
                            return false;
                        }
                    }

                    return true;
                }
            )
            ->values();
    }

    private function evidenceStatus(
        int $canonicalCurrentCount,
        int $unreconciledCurrentCount,
        int $canonicalActiveServiceCount
    ): string {
        if (
            $canonicalCurrentCount > 0
            && $unreconciledCurrentCount > 0
        ) {
            return 'partially_reconciled';
        }

        if ($canonicalCurrentCount > 0) {
            return 'canonical_service_observed_billing';
        }

        if ($unreconciledCurrentCount > 0) {
            return 'invoice_history_supported_not_reconciled';
        }

        if ($canonicalActiveServiceCount > 0) {
            return 'canonical_services_without_current_recurring_evidence';
        }

        return 'no_current_recurring_evidence';
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
