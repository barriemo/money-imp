<?php

namespace App\Domains\CommercialTruth\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CommercialAgreementReviewManifestService
{
    public function __construct(
        private readonly CommercialAgreementCoverageReviewQueueService $queue,
        private readonly CanonicalBillingObservationService $observations,
    ) {}

    /**
     * Return low-complexity services suitable for efficient human review.
     *
     * IMPORTANT:
     * These are review candidates only.
     * Observed billing is never promoted to contractual truth here.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function routine(
        CarbonImmutable $asOf
    ): Collection {
        $observations =
            $this->observations
                ->all()
                ->groupBy(
                    'client_service_id'
                );

        return $this->queue
            ->ready(
                $asOf
            )
            ->map(
                function ($item) use (
                    $observations
                ): ?array {
                    $rows =
                        $observations->get(
                            $item->clientServiceId,
                            collect()
                        );

                    $prices =
                        $rows
                            ->pluck(
                                'unit_price'
                            )
                            ->map(
                                fn ($value) => round(
                                    (float) $value,
                                    2
                                )
                            )
                            ->unique(
                                strict: true
                            )
                            ->values();

                    if (
                        $item->observedBillingState
                            !== 'current_recurring_observed'
                        || $item->observedFreshness
                            !== 'current'
                        || $item->observedCadence
                            !== 'monthly'
                        || $item->observedEvidenceCount
                            < 4
                        || $prices->count()
                            !== 1
                        || $item
                            ->observedCurrentMonthlyEquivalent
                            === null
                    ) {
                        return null;
                    }

                    return [
                        'client_service_id' => $item->clientServiceId,

                        'client_id' => $item->clientId,

                        'client' => $item->clientName,

                        'service' => $item->serviceName,

                        'proposed_action' => 'establish_terms',

                        'proposed_cadence' => 'monthly',

                        'proposed_amount_pence' => (int) round(
                            $item
                                ->observedCurrentMonthlyEquivalent
                            * 100
                        ),

                        'supported_current_monthly_equivalent' => $item
                            ->observedCurrentMonthlyEquivalent,

                        'observed_evidence_count' => $item->observedEvidenceCount,

                        'first_observed_on' => $item->firstObservedOn,

                        'last_observed_on' => $item->lastObservedOn,

                        'observed_unit_prices' => $prices->all(),

                        'warning' => 'PROPOSED FROM OBSERVED BILLING — NOT CONTRACT TRUTH',
                    ];
                }
            )
            ->filter()
            ->values()
            ->sortBy([
                [
                    'client',
                    'asc',
                ],
                [
                    'service',
                    'asc',
                ],
            ])
            ->values();
    }
}
