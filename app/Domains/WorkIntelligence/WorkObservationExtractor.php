<?php

namespace App\Domains\WorkIntelligence;

class WorkObservationExtractor
{
    public function extract(
        string $message
    ): WorkObservationCollection {
        $observations =
            collect();

        if (
            preg_match(
                '/(\d+)\s*hours?/i',
                $message,
                $matches
            )
        ) {
            $observations->push(
                new WorkObservation(
                    type: 'time_claimed',
                    value: (int) $matches[1],
                    confidence: 100,
                    metadata: [
                        'unit' => 'hours',
                    ]
                )
            );
        }

        if (
            str_contains(
                strtolower($message),
                'mml'
            )
        ) {
            $observations->push(
                new WorkObservation(
                    type: 'client_identified',
                    value: 'MML Law',
                    confidence: 90
                )
            );
        }

        return new WorkObservationCollection(
            $observations
        );
    }
}
