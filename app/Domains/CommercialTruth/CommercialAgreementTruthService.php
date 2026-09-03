<?php

namespace App\Domains\CommercialTruth;

use App\Models\CommercialAgreement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use LogicException;

class CommercialAgreementTruthService
{
    /**
     * Summarise persisted human-confirmed contractual assertions.
     *
     * Read only.
     *
     * Contracted total remains unknown until a future coverage layer
     * establishes that every relevant canonical service has received
     * an explicit terminal contract review.
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
         * Resolve the current head independently for each canonical
         * ClientService as at the requested date.
         *
         * This deliberately considers only assertions that are
         * effective by that date before resolving supersession.
         *
         * Therefore a future-dated successor does not prematurely
         * hide today's current assertion.
         */
        $currentAssertions =
            $this->currentAssertions(
                agreements: $agreements,
                asOf: $asOf
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

        /*
         * Presence of some agreement truth is not proof that every
         * relevant canonical service has been reviewed.
         */
        $contractedValueStatus =
            $agreements->isEmpty()
                ? 'not_established'
                : 'partially_established';

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
             * Persisted invoice-inference candidates are forbidden.
             */
            'candidate_count' => 0,

            'confirmed_count' => $confirmed->count(),

            /*
             * Known subtotal from current confirmed recurring
             * assertions.
             *
             * This is not claimed as the complete business-wide
             * contracted value.
             */
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
             * Critical:
             *
             * even after some terms are confirmed, business-wide
             * contracted monthly value stays unknown until explicit
             * coverage is complete.
             */
            'contracted_monthly_value' => null,

            'contracted_value_status' => $contractedValueStatus,

            'as_of_date' => $asOf->toDateString(),
        ];
    }

    private function currentAssertions(
        Collection $agreements,
        CarbonImmutable $asOf
    ): Collection {
        return $agreements
            ->groupBy(
                fn (
                    CommercialAgreement $agreement
                ) => (string) $agreement
                    ->client_service_id
            )
            ->map(
                function (
                    Collection $history
                ) use (
                    $asOf
                ): ?CommercialAgreement {
                    $eligible =
                        $history
                            ->filter(
                                fn (
                                    CommercialAgreement $agreement
                                ) => $this->effectiveOn(
                                    agreement: $agreement,
                                    asOf: $asOf
                                )
                            )
                            ->values();

                    if (
                        $eligible->isEmpty()
                    ) {
                        return null;
                    }

                    /*
                     * Only an eligible successor supersedes another
                     * assertion for this as-of date.
                     */
                    $supersededIds =
                        $eligible
                            ->pluck(
                                'supersedes_commercial_agreement_id'
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
                                    CommercialAgreement $agreement
                                ) => $supersededIds->has(
                                    (string) $agreement->id
                                )
                            )
                            ->values();

                    if (
                        $heads->count()
                        !== 1
                    ) {
                        throw new LogicException(
                            'Commercial agreement history does not resolve to exactly one current assertion for a canonical service.'
                        );
                    }

                    return $heads->first();
                }
            )
            ->filter()
            ->values();
    }

    private function effectiveOn(
        CommercialAgreement $agreement,
        CarbonImmutable $asOf
    ): bool {
        $starts =
            CarbonImmutable::instance(
                $agreement->effective_from
            );

        if (
            $starts->gt(
                $asOf
            )
        ) {
            return false;
        }

        if (
            $agreement->effective_to
            === null
        ) {
            return true;
        }

        return CarbonImmutable::instance(
            $agreement->effective_to
        )->gte(
            $asOf
        );
    }
}
