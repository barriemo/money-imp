<?php

namespace App\Domains\WorkIntelligence\Splitting;

use App\Domains\WorkIntelligence\WorkObservationCollection;

class WorkActivitySplitter
{
    public function split(
        WorkObservationCollection $observations
    ): WorkActivityCollection {
        $activities = collect();

        foreach (
            $observations->items as $observation
        ) {
            if (
                $observation->type !== 'work_described'
            ) {
                continue;
            }

            $activities->push(
                new WorkActivity(
                    description: (string) $observation->value,

                    minutes: (int) (
                        $observation->metadata['minutes']
                        ?? 0
                    ),

                    confidence: $observation->confidence,

                    metadata: [
                        'source' => 'conversation',
                    ]
                )
            );
        }

        return new WorkActivityCollection(
            $activities
        );
    }
}
