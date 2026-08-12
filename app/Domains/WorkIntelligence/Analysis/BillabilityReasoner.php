<?php

namespace App\Domains\WorkIntelligence\Analysis;

use App\Domains\WorkIntelligence\WorkObservationCollection;

class BillabilityReasoner
{
    public function assess(
        WorkObservationCollection $observations
    ): BillabilityAssessment {
        $signals = [];

        $types =
            $observations
                ->items
                ->pluck('type');

        if (
            $types->contains(
                'client_identified'
            )
        ) {
            $signals[] = 'client_identified';
        }

        if (
            $types->contains(
                'time_claimed'
            )
        ) {
            $signals[] = 'time_recorded';
        }

        if (
            $types->contains(
                'work_described'
            )
        ) {
            $signals[] = 'specific_work';
        }

        $score =
            count($signals) * 30;

        return new BillabilityAssessment(
            billable: $score >= 60,

            confidence: min(
                95,
                $score
            ),

            reason: $score >= 60
                ? 'Client-specific work with measurable activity signals.'
                : 'Insufficient evidence that this created recoverable client value.',

            signals: $signals
        );
    }
}
