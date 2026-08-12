<?php

namespace App\Domains\WorkIntelligence\Evidence;

use App\Domains\Evidence\EvidenceItem;
use App\Domains\WorkIntelligence\Analysis\BillabilityAssessment;
use App\Domains\WorkIntelligence\WorkObservationCollection;

class WorkEvidenceBuilder
{
    public function build(
        WorkObservationCollection $observations,
        BillabilityAssessment $assessment
    ): ?EvidenceItem {
        if (
            ! $assessment->billable
        ) {
            return null;
        }

        $client =
            $observations
                ->items
                ->first(
                    fn ($observation) => $observation->type
                        === 'client_identified'
                );

        $hours =
            $observations
                ->items
                ->first(
                    fn ($observation) => $observation->type
                        === 'time_claimed'
                );

        $work =
            $observations
                ->items
                ->first(
                    fn ($observation) => $observation->type
                        === 'work_described'
                );

        return new EvidenceItem(
            type: 'client_work_completed',

            source: 'staff',

            summary: sprintf(
                '%s spent %s hours on %s',
                $client?->value ?? 'Unknown client',
                $hours?->value ?? 'unknown',
                $work?->value ?? 'work activity'
            ),

            confidence: $assessment->confidence,

            subject: $client?->value,

            verified: false,

            metadata: [
                'hours' => $hours?->value,

                'activity' => $work?->value,

                'billable' => $assessment->billable,

                'signals' => $assessment->signals,
            ]
        );
    }
}
