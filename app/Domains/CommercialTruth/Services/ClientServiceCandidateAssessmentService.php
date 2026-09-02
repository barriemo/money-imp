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
        private readonly BillingEvidenceAssessmentService $billingEvidence,
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

        $billingEvidence =
            $this->billingEvidence
                ->assess(
                    cadence: $candidate->cadence,
                    cadenceConfidence: $candidate
                        ->cadenceConfidence,
                    lastObservedOn: $candidate
                        ->lastObservedOn,
                    monthlyEquivalent: $candidate
                        ->monthlyEquivalent,
                    asOf: $asOf
                );

        $daysSinceLastObservation =
            $billingEvidence
                ->daysSinceLastObservation;

        $cadenceEstablished =
            $billingEvidence
                ->cadenceEstablished;

        $recurringEvidence =
            $billingEvidence
                ->recurringEvidence;

        $freshness =
            $billingEvidence
                ->freshness;

        $currentMonthlyEquivalent =
            $candidate->isCompositeCandidate()
                ? null
                : $billingEvidence
                    ->currentMonthlyEquivalent;

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

    private function promotionReadiness(
        ClientServiceCandidate $candidate,
        bool $cadenceEstablished,
        string $freshness
    ): string {
        if (
            $candidate->isCompositeCandidate()
        ) {
            return 'needs_commercial_review';
        }

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
            $candidate->isCompositeCandidate()
        ) {
            $reasons[] = sprintf(
                'Composite commercial evidence spans %d detected activity families: %s. Human commercial review is required to establish whether this is one bundled service or requires monetary decomposition before canonical promotion.',
                count(
                    $candidate
                        ->commercialComponents
                ),
                implode(
                    ', ',
                    $candidate
                        ->commercialComponents
                )
            );

            return $reasons;
        }

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
