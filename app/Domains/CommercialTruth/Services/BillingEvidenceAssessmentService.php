<?php

namespace App\Domains\CommercialTruth\Services;

use App\Domains\CommercialTruth\DTO\BillingEvidenceAssessment;
use Carbon\CarbonImmutable;

final class BillingEvidenceAssessmentService
{
    public function assess(
        string $cadence,
        int $cadenceConfidence,
        ?string $lastObservedOn,
        float $monthlyEquivalent,
        ?CarbonImmutable $asOf = null
    ): BillingEvidenceAssessment {
        $asOf ??=
            CarbonImmutable::today();

        $daysSinceLastObservation =
            $this->daysSinceLastObservation(
                $lastObservedOn,
                $asOf
            );

        $cadenceEstablished =
            in_array(
                $cadence,
                [
                    'monthly',
                    'annual',
                ],
                true
            )
            && $cadenceConfidence >= 80;

        $recurringEvidence =
            $cadenceEstablished
            && in_array(
                $cadence,
                [
                    'monthly',
                    'annual',
                ],
                true
            );

        $freshness =
            $this->freshness(
                cadence: $cadence,
                daysSinceLastObservation: $daysSinceLastObservation
            );

        $currentMonthlyEquivalent =
            $recurringEvidence
            && $freshness === 'current'
                ? round(
                    $monthlyEquivalent,
                    2
                )
                : null;

        return new BillingEvidenceAssessment(
            daysSinceLastObservation: $daysSinceLastObservation,
            freshness: $freshness,
            cadenceEstablished: $cadenceEstablished,
            recurringEvidence: $recurringEvidence,
            currentMonthlyEquivalent: $currentMonthlyEquivalent,
        );
    }

    private function daysSinceLastObservation(
        ?string $lastObservedOn,
        CarbonImmutable $asOf
    ): ?int {
        if ($lastObservedOn === null) {
            return null;
        }

        $lastObserved =
            CarbonImmutable::parse(
                $lastObservedOn
            )->startOfDay();

        $asOf =
            $asOf->startOfDay();

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

    private function freshness(
        string $cadence,
        ?int $daysSinceLastObservation
    ): string {
        if (
            $daysSinceLastObservation
            === null
        ) {
            return 'unknown';
        }

        return match ($cadence) {
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
}
