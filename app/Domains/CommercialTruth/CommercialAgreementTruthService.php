<?php

namespace App\Domains\CommercialTruth;

use App\Domains\CommercialTruth\Services\CommercialAgreementCoverageService;
use App\Domains\CommercialTruth\Services\CommercialAgreementCurrentAssertionService;
use App\Models\CommercialAgreement;
use Carbon\CarbonImmutable;

class CommercialAgreementTruthService
{
    public function __construct(
        private readonly CommercialAgreementCurrentAssertionService $currentAssertions,
        private readonly CommercialAgreementCoverageService $coverage,
    ) {}

    /**
     * Summarise persisted human-confirmed contractual assertions.
     *
     * Read only.
     *
     * Individual confirmed assertions may provide a known subtotal,
     * but the business-wide contracted monthly total remains null
     * until every effective active canonical service has a currently
     * valid terminal coverage review.
     */
    public function summary(
        ?CarbonImmutable $asOf = null
    ): array {
        $asOf ??=
            CarbonImmutable::today();

        $agreements =
            CommercialAgreement::query()
                ->with([
                    'client',
                    'clientService',
                    'evidence',
                ])
                ->orderBy(
                    'created_at'
                )
                ->get();

        /*
         * One canonical resolver now owns agreement-head semantics.
         *
         * This prevents contract truth and coverage truth from
         * independently implementing supersession/as-of rules.
         */
        $currentAssertions =
            $this->currentAssertions
                ->all(
                    $asOf
                );

        $confirmed =
            $currentAssertions
                ->where(
                    'status',
                    'confirmed'
                )
                ->values();

        $terminated =
            $currentAssertions
                ->where(
                    'status',
                    'terminated'
                )
                ->values();

        $monthly =
            $confirmed
                ->where(
                    'cadence',
                    'monthly'
                );

        $quarterly =
            $confirmed
                ->where(
                    'cadence',
                    'quarterly'
                );

        $annual =
            $confirmed
                ->where(
                    'cadence',
                    'annual'
                );

        $oneOff =
            $confirmed
                ->where(
                    'cadence',
                    'one_off'
                );

        $recurring =
            $confirmed
                ->whereIn(
                    'cadence',
                    [
                        'monthly',
                        'quarterly',
                        'annual',
                    ]
                )
                ->values();

        /*
         * Known subtotal from all current confirmed recurring
         * agreement assertions.
         *
         * This remains useful before coverage is complete but is not
         * represented as the complete business-wide contracted total.
         */
        $knownConfirmedMonthlyEquivalent =
            round(
                (float) $recurring
                    ->sum(
                        fn (
                            CommercialAgreement $agreement
                        ) => (float) (
                            $agreement
                                ->monthly_equivalent
                            ?? 0
                        )
                    ),
                2
            );

        $coverage =
            $this->coverage
                ->summary(
                    $asOf
                );

        /*
         * The exact business-wide reporting total may use only
         * agreement assertions represented by currently valid
         * confirmed_terms coverage reviews.
         *
         * no_current_contract is an explicit terminal zero.
         */
        $coveredAgreementIds =
            $coverage[
                'terminal_reviews'
            ]
                ->where(
                    'outcome',
                    CommercialAgreementCoverageService::OUTCOME_CONFIRMED_TERMS
                )
                ->pluck(
                    'commercial_agreement_id'
                )
                ->filter()
                ->map(
                    fn ($id) => (string) $id
                )
                ->flip();

        $coveredRecurringMonthlyEquivalent =
            round(
                (float) $currentAssertions
                    ->filter(
                        fn (
                            CommercialAgreement $agreement
                        ) => $agreement->status
                                === 'confirmed'
                            && in_array(
                                $agreement->cadence,
                                [
                                    'monthly',
                                    'quarterly',
                                    'annual',
                                ],
                                true
                            )
                            && $coveredAgreementIds->has(
                                (string) $agreement->id
                            )
                    )
                    ->sum(
                        fn (
                            CommercialAgreement $agreement
                        ) => (float) (
                            $agreement
                                ->monthly_equivalent
                            ?? 0
                        )
                    ),
                2
            );

        /*
         * This is the critical unknown-vs-zero gate.
         *
         * Incomplete coverage -> null.
         *
         * Complete coverage:
         *   confirmed_terms contribute their monthly equivalents;
         *   no_current_contract contributes explicit zero.
         *
         * Therefore complete all-zero coverage legitimately returns
         * 0.0 rather than null.
         */
        $contractedMonthlyValue =
            $coverage['complete']
                ? $coveredRecurringMonthlyEquivalent
                : null;

        $contractedValueStatus =
            match (true) {
                $coverage['complete'] => 'reconciled',

                $agreements->isEmpty()
                && $coverage[
                    'current_reviews'
                ]->isEmpty() => 'not_established',

                default => 'partially_established',
            };

        $startedAssertionCount =
            $agreements
                ->filter(
                    fn (
                        CommercialAgreement $agreement
                    ) => CarbonImmutable::instance(
                        $agreement
                            ->effective_from
                    )->lte(
                        $asOf
                    )
                )
                ->count();

        $futureAssertionCount =
            $agreements->count()
            - $startedAssertionCount;

        return [
            'agreements' => $agreements,

            'current_assertions' => $currentAssertions,

            'confirmed_agreements' => $confirmed,

            'agreement_count' => $agreements->count(),

            'current_assertion_count' => $currentAssertions->count(),

            'historical_assertion_count' => max(
                0,
                $startedAssertionCount
                - $currentAssertions->count()
            ),

            'future_assertion_count' => $futureAssertionCount,

            'monthly_count' => $monthly->count(),

            'quarterly_count' => $quarterly->count(),

            'annual_count' => $annual->count(),

            'one_off_count' => $oneOff->count(),

            'terminated_count' => $terminated->count(),

            /*
             * Persisted invoice-inference candidates remain forbidden.
             */
            'candidate_count' => 0,

            'confirmed_count' => $confirmed->count(),

            'confirmed_recurring_monthly_equivalent' => $knownConfirmedMonthlyEquivalent,

            /*
             * Backward-compatible known subtotal.
             */
            'recurring_monthly_equivalent' => $knownConfirmedMonthlyEquivalent,

            'recurring_annual_equivalent' => round(
                $knownConfirmedMonthlyEquivalent
                * 12,
                2
            ),

            /*
             * Sum represented by currently valid confirmed_terms
             * coverage reviews, even while total coverage is
             * incomplete.
             */
            'covered_confirmed_recurring_monthly_equivalent' => $coveredRecurringMonthlyEquivalent,

            'contracted_monthly_value' => $contractedMonthlyValue,

            'contracted_value_status' => $contractedValueStatus,

            'coverage_scope_count' => $coverage['scope_count'],

            'coverage_reviewed_count' => $coverage['reviewed_count'],

            'coverage_terminal_count' => $coverage['terminal_count'],

            'coverage_confirmed_terms_count' => $coverage[
                    'confirmed_terms_count'
                ],

            'coverage_no_current_contract_count' => $coverage[
                    'no_current_contract_count'
                ],

            'coverage_needs_more_evidence_count' => $coverage[
                    'needs_more_evidence_count'
                ],

            'coverage_stale_terminal_review_count' => $coverage[
                    'stale_terminal_review_count'
                ],

            'coverage_unresolved_count' => $coverage[
                    'unresolved_count'
                ],

            'coverage_complete' => $coverage['complete'],

            'coverage_status' => $coverage['status'],

            'as_of_date' => $asOf->toDateString(),
        ];
    }
}
