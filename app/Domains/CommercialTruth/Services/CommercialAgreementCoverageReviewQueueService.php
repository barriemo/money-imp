<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\CanonicalServiceObservedBilling;
use App\Domains\CommercialTruth\DTO\CommercialAgreementCoverageReviewCandidate;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CommercialAgreementCoverageReviewQueueService
{
    public function __construct(
        private readonly CommercialAgreementCoverageService $coverage,
        private readonly CommercialAgreementCurrentAssertionService $currentAgreements,
        private readonly CanonicalServiceObservedBillingService $observedBilling,
    ) {}

    /**
     * Read-only human review queue.
     *
     * @return Collection<int, CommercialAgreementCoverageReviewCandidate>
     */
    public function ready(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??=
            CarbonImmutable::today();

        $coverage =
            $this->coverage
                ->summary(
                    $asOf
                );

        /** @var Collection<int, ClientService> $scope */
        $scope =
            $coverage[
                'scoped_services'
            ];

        /** @var Collection<int, ClientService> $unresolved */
        $unresolved =
            $coverage[
                'unresolved_services'
            ];

        if (
            $unresolved->isEmpty()
        ) {
            return collect();
        }

        $clientNames =
            Client::query()
                ->whereIn(
                    'id',
                    $scope
                        ->pluck(
                            'client_id'
                        )
                        ->all()
                )
                ->pluck(
                    'name',
                    'id'
                );

        $currentReviews =
            $coverage[
                'current_reviews'
            ]
                ->keyBy(
                    fn (
                        CommercialAgreementCoverageReview $review
                    ) => (string) $review
                        ->client_service_id
                );

        $staleTerminalServiceIds =
            $coverage[
                'stale_terminal_reviews'
            ]
                ->pluck(
                    'client_service_id'
                )
                ->map(
                    fn ($id) => (string) $id
                )
                ->flip();

        $currentAgreements =
            $this->currentAgreements
                ->all(
                    $asOf
                )
                ->keyBy(
                    fn (
                        CommercialAgreement $agreement
                    ) => (string) $agreement
                        ->client_service_id
                );

        $observedBilling =
            $this->observedBilling
                ->all(
                    $asOf
                )
                ->keyBy(
                    fn (
                        CanonicalServiceObservedBilling $billing
                    ) => $billing
                        ->clientServiceId
                );

        return $unresolved
            ->map(
                function (
                    ClientService $service
                ) use (
                    $clientNames,
                    $currentReviews,
                    $staleTerminalServiceIds,
                    $currentAgreements,
                    $observedBilling
                ): CommercialAgreementCoverageReviewCandidate {
                    $serviceId =
                        (string) $service->id;

                    /** @var CommercialAgreementCoverageReview|null $review */
                    $review =
                        $currentReviews->get(
                            $serviceId
                        );

                    /** @var CommercialAgreement|null $agreement */
                    $agreement =
                        $currentAgreements->get(
                            $serviceId
                        );

                    /** @var CanonicalServiceObservedBilling|null $billing */
                    $billing =
                        $observedBilling->get(
                            $serviceId
                        );

                    $coverageState =
                        $this->coverageState(
                            review: $review,

                            staleTerminal: $staleTerminalServiceIds
                                ->has(
                                    $serviceId
                                )
                        );

                    $observedState =
                        $this->observedBillingState(
                            $billing
                        );

                    $priority =
                        $this->priority(
                            coverageState: $coverageState,

                            agreement: $agreement,

                            billing: $billing
                        );

                    return new CommercialAgreementCoverageReviewCandidate(
                        clientServiceId: $serviceId,

                        clientId: (string) $service
                            ->client_id,

                        clientName: (string) (
                            $clientNames[
                                $service->client_id
                            ]
                            ?? 'Unknown client'
                        ),

                        serviceName: $service->name,

                        serviceType: $service->type,

                        coverageState: $coverageState,

                        priority: $priority,

                        priorityReason: $this->priorityReason(
                            coverageState: $coverageState,

                            agreement: $agreement,

                            billing: $billing
                        ),

                        coverageReviewId: $review !== null
                                ? (string) $review->id
                                : null,

                        coverageOutcome: $review?->outcome,

                        coverageEffectiveFrom: $review
                            ?->effective_from
                            ?->toDateString(),

                        observedBillingState: $observedState,

                        observedEvidenceCount: $billing?->evidenceCount
                            ?? 0,

                        observedCadence: $billing?->cadence,

                        observedFreshness: $billing?->freshness,

                        firstObservedOn: $billing?->firstObservedOn,

                        lastObservedOn: $billing?->lastObservedOn,

                        observedCurrentMonthlyEquivalent: $billing
                            ?->currentMonthlyEquivalent,

                        currentAgreementId: $agreement !== null
                                ? (string) $agreement->id
                                : null,

                        currentAgreementStatus: $agreement?->status,

                        currentAgreementCadence: $agreement?->cadence,

                        currentAgreementAmountPence: $agreement
                            ?->contracted_amount_pence,

                        currentAgreementMonthlyEquivalent: $agreement
                            ?->monthly_equivalent
                                !== null
                                    ? (float) $agreement
                                        ->monthly_equivalent
                                    : null,

                        availableDecisions: $this->availableDecisions(
                            $agreement
                        ),
                    );
                }
            )
            ->sort(
                function (
                    CommercialAgreementCoverageReviewCandidate $left,
                    CommercialAgreementCoverageReviewCandidate $right
                ): int {
                    $priority =
                        $right->priority
                        <=>
                        $left->priority;

                    if (
                        $priority !== 0
                    ) {
                        return $priority;
                    }

                    $client =
                        strcasecmp(
                            $left->clientName,
                            $right->clientName
                        );

                    if (
                        $client !== 0
                    ) {
                        return $client;
                    }

                    return strcasecmp(
                        $left->serviceName,
                        $right->serviceName
                    );
                }
            )
            ->values();
    }

    private function coverageState(
        ?CommercialAgreementCoverageReview $review,
        bool $staleTerminal
    ): string {
        if (
            $staleTerminal
        ) {
            return 'stale_terminal_review';
        }

        if (
            $review === null
        ) {
            return 'unreviewed';
        }

        if (
            $review->outcome
            === CommercialAgreementCoverageService::OUTCOME_NEEDS_MORE_EVIDENCE
        ) {
            return 'needs_more_evidence';
        }

        /*
         * The coverage service should already have excluded valid
         * terminal reviews from unresolved_services.
         */
        return 'unresolved_review';
    }

    private function observedBillingState(
        ?CanonicalServiceObservedBilling $billing
    ): string {
        if (
            $billing === null
        ) {
            return 'no_observed_billing';
        }

        if (
            $billing
                ->currentMonthlyEquivalent
            !== null
        ) {
            return 'current_recurring_observed';
        }

        return 'observed_not_current_recurring';
    }

    private function priority(
        string $coverageState,
        ?CommercialAgreement $agreement,
        ?CanonicalServiceObservedBilling $billing
    ): int {
        if (
            $coverageState
            === 'stale_terminal_review'
        ) {
            return 100;
        }

        if (
            $coverageState
            === 'needs_more_evidence'
        ) {
            return 95;
        }

        /*
         * If confirmed agreement truth already exists but coverage
         * does not, the human can close the denominator without
         * inventing contractual terms.
         */
        if (
            $agreement !== null
            && $agreement->status
                === 'confirmed'
        ) {
            return 92;
        }

        /*
         * No billing evidence at all is a high-priority unknown.
         */
        if (
            $billing === null
        ) {
            return 90;
        }

        /*
         * Stale, unknown-cadence and otherwise non-current billing
         * deserves review ahead of ordinary current recurring billing.
         *
         * This is where the Alpha Projects annual-domain case lands.
         */
        if (
            $billing
                ->currentMonthlyEquivalent
                === null
            || $billing->cadence
                === 'unknown'
            || $billing->freshness
                !== 'current'
        ) {
            return 85;
        }

        return 70;
    }

    private function priorityReason(
        string $coverageState,
        ?CommercialAgreement $agreement,
        ?CanonicalServiceObservedBilling $billing
    ): string {
        if (
            $coverageState
            === 'stale_terminal_review'
        ) {
            return 'A previous terminal coverage decision no longer matches current contractual truth.';
        }

        if (
            $coverageState
            === 'needs_more_evidence'
        ) {
            return 'A human review already identified missing contractual evidence.';
        }

        if (
            $agreement !== null
            && $agreement->status
                === 'confirmed'
        ) {
            return 'Current confirmed contractual terms exist but coverage has not yet been closed.';
        }

        if (
            $billing === null
        ) {
            return 'Active canonical service has no observed billing evidence; contractual status requires explicit review.';
        }

        if (
            $billing
                ->currentMonthlyEquivalent
                === null
        ) {
            return 'Observed billing exists but does not support a current recurring monthly-equivalent value.';
        }

        if (
            $billing->cadence
            === 'unknown'
        ) {
            return 'Observed billing cadence is unknown.';
        }

        if (
            $billing->freshness
            !== 'current'
        ) {
            return 'Observed billing is not current.';
        }

        return 'Current recurring billing exists, but invoice evidence is not contractual truth.';
    }

    private function availableDecisions(
        ?CommercialAgreement $agreement
    ): array {
        if (
            $agreement !== null
            && $agreement->status
                === 'confirmed'
        ) {
            return [
                CommercialAgreementCoverageService::OUTCOME_CONFIRMED_TERMS,
                CommercialAgreementCoverageService::OUTCOME_NEEDS_MORE_EVIDENCE,
            ];
        }

        return [
            CommercialAgreementCoverageService::OUTCOME_NO_CURRENT_CONTRACT,
            CommercialAgreementCoverageService::OUTCOME_NEEDS_MORE_EVIDENCE,
        ];
    }
}
