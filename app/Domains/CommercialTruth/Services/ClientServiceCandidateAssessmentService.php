<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\ClientServiceCandidate;
use App\Domains\CommercialTruth\DTO\ClientServiceCandidateAssessment;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ClientServiceCandidateAssessmentService
{
    public function __construct(
        private readonly ClientServiceCandidateService $candidates,
    ) {}

    /**
     * @return Collection<int, ClientServiceCandidateAssessment>
     */
    public function all(
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??= CarbonImmutable::today();

        return $this->candidates
            ->all()
            ->map(
                fn (ClientServiceCandidate $candidate) => $this->assess(
                    $candidate,
                    $asOf
                )
            )
            ->values();
    }

    /**
     * @return Collection<int, ClientServiceCandidateAssessment>
     */
    public function forClient(
        Client $client,
        ?CarbonImmutable $asOf = null
    ): Collection {
        $asOf ??= CarbonImmutable::today();

        return $this->candidates
            ->forClient($client)
            ->map(
                fn (ClientServiceCandidate $candidate) => $this->assess(
                    $candidate,
                    $asOf
                )
            )
            ->values();
    }

    public function assess(
        ClientServiceCandidate $candidate,
        ?CarbonImmutable $asOf = null
    ): ClientServiceCandidateAssessment {
        $asOf ??= CarbonImmutable::today();

        $daysSinceLastObservation =
            $this->daysSinceLastObservation(
                $candidate,
                $asOf
            );

        $cadenceEstablished =
            $this->cadenceEstablished(
                $candidate
            );

        $recurringEvidence =
            $cadenceEstablished
            && in_array(
                $candidate->cadence,
                [
                    'monthly',
                    'annual',
                ],
                true
            );

        $freshness = $this->freshness(
            candidate: $candidate,
            daysSinceLastObservation: $daysSinceLastObservation,
        );

        /*
         * Never convert stale or historical evidence into
         * supposedly current recurring revenue.
         *
         * Null means current recurring value has not been
         * established from sufficiently fresh evidence.
         */
        $currentMonthlyEquivalent =
            $recurringEvidence
            && $freshness === 'current'
                ? round(
                    $candidate->monthlyEquivalent,
                    2
                )
                : null;

        $promotionReadiness =
            $this->promotionReadiness(
                candidate: $candidate,
                cadenceEstablished: $cadenceEstablished,
                freshness: $freshness,
            );

        return new ClientServiceCandidateAssessment(
            candidate: $candidate,
            asOfDate: $asOf->toDateString(),
            daysSinceLastObservation: $daysSinceLastObservation,
            freshness: $freshness,
            cadenceEstablished: $cadenceEstablished,
            recurringEvidence: $recurringEvidence,
            currentMonthlyEquivalent: $currentMonthlyEquivalent,
            promotionReadiness: $promotionReadiness,
            reasons: $this->reasons(
                candidate: $candidate,
                cadenceEstablished: $cadenceEstablished,
                freshness: $freshness,
                daysSinceLastObservation: $daysSinceLastObservation,
            ),
        );
    }

    private function daysSinceLastObservation(
        ClientServiceCandidate $candidate,
        CarbonImmutable $asOf
    ): ?int {
        if (
            $candidate->lastObservedOn
            === null
        ) {
            return null;
        }

        $lastObserved = CarbonImmutable::parse(
            $candidate->lastObservedOn
        )->startOfDay();

        $asOf = $asOf->startOfDay();

        /*
         * Future-dated evidence should not produce a negative
         * evidence age. It is treated as zero days old and can
         * be investigated separately if encountered.
         */
        if (
            $lastObserved->greaterThan(
                $asOf
            )
        ) {
            return 0;
        }

        return (int) $lastObserved
            ->diffInDays(
                $asOf
            );
    }

    private function cadenceEstablished(
        ClientServiceCandidate $candidate
    ): bool {
        return in_array(
            $candidate->cadence,
            [
                'monthly',
                'annual',
            ],
            true
        )
            && $candidate
                ->cadenceConfidence >= 80;
    }

    private function freshness(
        ClientServiceCandidate $candidate,
        ?int $daysSinceLastObservation
    ): string {
        if (
            $daysSinceLastObservation
            === null
        ) {
            return 'unknown';
        }

        return match (
            $candidate->cadence
        ) {
            'monthly' => match (true) {
                $daysSinceLastObservation <= 45 => 'current',

                $daysSinceLastObservation <= 90 => 'recently_observed',

                $daysSinceLastObservation <= 180 => 'stale',

                default => 'historical',
            },

            'annual' => match (true) {
                $daysSinceLastObservation <= 400 => 'current',

                $daysSinceLastObservation <= 460 => 'recently_observed',

                $daysSinceLastObservation <= 550 => 'stale',

                default => 'historical',
            },

            default => match (true) {
                $daysSinceLastObservation <= 90 => 'recently_observed',

                $daysSinceLastObservation <= 365 => 'stale',

                default => 'historical',
            },
        };
    }

    private function promotionReadiness(
        ClientServiceCandidate $candidate,
        bool $cadenceEstablished,
        string $freshness
    ): string {
        if (
            ! $candidate->isServiceCandidate()
        ) {
            return 'not_service_candidate';
        }

        if (
            ! $cadenceEstablished
        ) {
            return 'needs_more_evidence';
        }

        if (
            in_array(
                $freshness,
                [
                    'current',
                    'recently_observed',
                ],
                true
            )
        ) {
            /*
             * This means suitable for human reconciliation,
             * never automatic canonical promotion.
             */
            return 'ready_for_review';
        }

        return 'needs_more_evidence';
    }

    private function reasons(
        ClientServiceCandidate $candidate,
        bool $cadenceEstablished,
        string $freshness,
        ?int $daysSinceLastObservation
    ): array {
        $reasons = [
            sprintf(
                'Classification confidence is %d%%.',
                $candidate
                    ->classificationConfidence
            ),
        ];

        if (
            ! $candidate->isServiceCandidate()
        ) {
            $reasons[] =
                'Commercial treatment is not eligible for canonical client service review.';

            return $reasons;
        }

        if ($cadenceEstablished) {
            $reasons[] = sprintf(
                '%s cadence is established at %d%% confidence.',
                ucfirst(
                    $candidate->cadence
                ),
                $candidate
                    ->cadenceConfidence
            );
        } else {
            $reasons[] =
                'Recurring billing cadence is not yet established.';
        }

        if (
            $daysSinceLastObservation
            !== null
        ) {
            $reasons[] = sprintf(
                'Latest billing evidence is %d day%s old and is assessed as %s.',
                $daysSinceLastObservation,
                $daysSinceLastObservation === 1
                    ? ''
                    : 's',
                str_replace(
                    '_',
                    ' ',
                    $freshness
                )
            );
        } else {
            $reasons[] =
                'No dated billing evidence is available.';
        }

        return $reasons;
    }
}
