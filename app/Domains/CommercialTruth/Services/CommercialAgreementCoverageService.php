<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\ClientService;
use App\Models\CommercialAgreement;
use App\Models\CommercialAgreementCoverageReview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

final class CommercialAgreementCoverageService
{
    public const OUTCOME_CONFIRMED_TERMS =
        'confirmed_terms';

    public const OUTCOME_NO_CURRENT_CONTRACT =
        'no_current_contract';

    public const OUTCOME_NEEDS_MORE_EVIDENCE =
        'needs_more_evidence';

    public const TERMINAL_OUTCOMES = [
        self::OUTCOME_CONFIRMED_TERMS,
        self::OUTCOME_NO_CURRENT_CONTRACT,
    ];

    public function __construct(
        private readonly CommercialAgreementCurrentAssertionService $currentAgreements,
    ) {}

    public function summary(
        ?CarbonImmutable $asOf = null
    ): array {
        $asOf ??=
            CarbonImmutable::today();

        $scope =
            $this->scopedServices(
                $asOf
            );

        $serviceIds =
            $scope
                ->pluck('id')
                ->map(
                    fn ($id) => (string) $id
                )
                ->values();

        $reviews =
            $serviceIds->isEmpty()
                ? collect()
                : CommercialAgreementCoverageReview::query()
                    ->with([
                        'clientService',
                        'commercialAgreement',
                    ])
                    ->whereIn(
                        'client_service_id',
                        $serviceIds->all()
                    )
                    ->orderBy(
                        'created_at'
                    )
                    ->get();

        $currentReviews =
            $this->currentReviews(
                reviews: $reviews,
                asOf: $asOf
            );

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

        foreach (
            $currentReviews as $review
        ) {
            $this->assertConsistent(
                review: $review,
                currentAgreement: $currentAgreements->get(
                    (string) $review
                        ->client_service_id
                )
            );
        }

        $terminal =
            $currentReviews
                ->filter(
                    fn (
                        CommercialAgreementCoverageReview $review
                    ) => in_array(
                        $review->outcome,
                        self::TERMINAL_OUTCOMES,
                        true
                    )
                )
                ->values();

        $confirmedTerms =
            $currentReviews
                ->where(
                    'outcome',
                    self::OUTCOME_CONFIRMED_TERMS
                )
                ->values();

        $noCurrentContract =
            $currentReviews
                ->where(
                    'outcome',
                    self::OUTCOME_NO_CURRENT_CONTRACT
                )
                ->values();

        $needsMoreEvidence =
            $currentReviews
                ->where(
                    'outcome',
                    self::OUTCOME_NEEDS_MORE_EVIDENCE
                )
                ->values();

        $currentByService =
            $currentReviews
                ->keyBy(
                    fn (
                        CommercialAgreementCoverageReview $review
                    ) => (string) $review
                        ->client_service_id
                );

        $unresolved =
            $scope
                ->filter(
                    function (
                        ClientService $service
                    ) use (
                        $currentByService
                    ): bool {
                        $review =
                            $currentByService
                                ->get(
                                    (string) $service->id
                                );

                        return $review === null
                            || ! in_array(
                                $review->outcome,
                                self::TERMINAL_OUTCOMES,
                                true
                            );
                    }
                )
                ->values();

        /*
         * Zero services does not prove a £0 contracted business.
         *
         * A complete denominator must first exist.
         */
        $complete =
            $scope->isNotEmpty()
            && $terminal->count()
                === $scope->count();

        $status =
            match (true) {
                $scope->isEmpty() => 'no_active_services',

                $currentReviews->isEmpty() => 'not_started',

                $complete => 'complete',

                default => 'incomplete',
            };

        return [
            'as_of_date' => $asOf->toDateString(),

            'scoped_services' => $scope,

            'current_reviews' => $currentReviews,

            'terminal_reviews' => $terminal,

            'unresolved_services' => $unresolved,

            'scope_count' => $scope->count(),

            'reviewed_count' => $currentReviews->count(),

            'terminal_count' => $terminal->count(),

            'confirmed_terms_count' => $confirmedTerms->count(),

            'no_current_contract_count' => $noCurrentContract->count(),

            'needs_more_evidence_count' => $needsMoreEvidence->count(),

            'unreviewed_count' => max(
                0,
                $scope->count()
                - $currentReviews->count()
            ),

            'unresolved_count' => $unresolved->count(),

            'complete' => $complete,

            'status' => $status,
        ];
    }

    /**
     * @return Collection<int, ClientService>
     */
    private function scopedServices(
        CarbonImmutable $asOf
    ): Collection {
        $date =
            $asOf->toDateString();

        return ClientService::query()
            ->where(
                'status',
                'active'
            )
            ->where(
                function ($query) use ($date): void {
                    $query
                        ->whereNull(
                            'starts_on'
                        )
                        ->orWhereDate(
                            'starts_on',
                            '<=',
                            $date
                        );
                }
            )
            ->where(
                function ($query) use ($date): void {
                    $query
                        ->whereNull(
                            'ends_on'
                        )
                        ->orWhereDate(
                            'ends_on',
                            '>=',
                            $date
                        );
                }
            )
            ->orderBy(
                'client_id'
            )
            ->orderBy(
                'name'
            )
            ->get();
    }

    /**
     * @param  Collection<int, CommercialAgreementCoverageReview>  $reviews
     * @return Collection<int, CommercialAgreementCoverageReview>
     */
    private function currentReviews(
        Collection $reviews,
        CarbonImmutable $asOf
    ): Collection {
        return $reviews
            ->groupBy(
                fn (
                    CommercialAgreementCoverageReview $review
                ) => (string) $review
                    ->client_service_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?CommercialAgreementCoverageReview {
                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    CommercialAgreementCoverageReview $review
                                ) => CarbonImmutable::instance(
                                    $review->effective_from
                                )->lte(
                                    $asOf
                                )
                            )
                            ->values();

                    if (
                        $eligible->isEmpty()
                    ) {
                        return null;
                    }

                    /*
                     * Future coverage decisions do not hide today's
                     * current review.
                     */
                    $supersededIds =
                        $eligible
                            ->pluck(
                                'supersedes_commercial_agreement_coverage_review_id'
                            )
                            ->filter()
                            ->map(
                                fn ($id) => (string) $id
                            )
                            ->flip();

                    $heads =
                        $eligible
                            ->reject(
                                fn (
                                    CommercialAgreementCoverageReview $review
                                ) => $supersededIds->has(
                                    (string) $review->id
                                )
                            )
                            ->values();

                    if (
                        $heads->count()
                        !== 1
                    ) {
                        throw new LogicException(
                            'Commercial agreement coverage history does not resolve to exactly one current review for a canonical service.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    private function assertConsistent(
        CommercialAgreementCoverageReview $review,
        ?CommercialAgreement $currentAgreement
    ): void {
        if (
            $review->outcome
            === self::OUTCOME_CONFIRMED_TERMS
        ) {
            if (
                $review->commercial_agreement_id
                === null
                || $currentAgreement === null
                || (string) $review
                    ->commercial_agreement_id
                    !== (string) $currentAgreement->id
                || $currentAgreement->status
                    !== 'confirmed'
            ) {
                throw new LogicException(
                    'Confirmed contract coverage does not reference the current confirmed commercial agreement assertion.'
                );
            }

            return;
        }

        if (
            $review->commercial_agreement_id
            !== null
        ) {
            throw new LogicException(
                'Only confirmed_terms coverage may reference a commercial agreement.'
            );
        }

        if (
            $review->outcome
            === self::OUTCOME_NO_CURRENT_CONTRACT
            && $currentAgreement !== null
            && $currentAgreement->status
                === 'confirmed'
        ) {
            throw new LogicException(
                'No-current-contract coverage contradicts a current confirmed commercial agreement.'
            );
        }
    }
}
