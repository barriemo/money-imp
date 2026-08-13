<?php

namespace App\Domains\BusinessBrain\Observations\History;

use App\Domains\BusinessBrain\Observations\BusinessObservation;
use Illuminate\Support\Collection;

class BusinessObservationChangeDetector
{
    public function compare(
        BusinessObservationSnapshot $previous,
        BusinessObservationSnapshot $current
    ): Collection {
        $old =
            $previous
                ->observations
                ->keyBy(
                    fn (BusinessObservation $observation) => $this->key(
                        $observation
                    )
                );

        $new =
            $current
                ->observations
                ->keyBy(
                    fn (BusinessObservation $observation) => $this->key(
                        $observation
                    )
                );

        $changes =
            collect();

        foreach ($new as $key => $observation) {
            if (! $old->has($key)) {
                $changes->push(
                    new BusinessObservationChange(
                        type: 'new',

                        observation: $observation
                    )
                );

                continue;
            }

            $previousObservation =
                $old->get(
                    $key
                );

            if (
                $this->hasWorsened(
                    $previousObservation,
                    $observation
                )
            ) {
                $changes->push(
                    new BusinessObservationChange(
                        type: 'worsened',

                        observation: $observation,

                        previous: $previousObservation
                    )
                );
            }

            if (
                $this->hasImproved(
                    $previousObservation,
                    $observation
                )
            ) {
                $changes->push(
                    new BusinessObservationChange(
                        type: 'improved',

                        observation: $observation,

                        previous: $previousObservation
                    )
                );
            }
        }

        foreach ($old as $key => $observation) {
            if (! $new->has($key)) {
                $changes->push(
                    new BusinessObservationChange(
                        type: 'resolved',

                        observation: $observation
                    )
                );
            }
        }

        return $changes
            ->sortByDesc(
                fn (BusinessObservationChange $change) => $change
                    ->observation
                    ->priority
            )
            ->values();
    }

    private function key(
        BusinessObservation $observation
    ): string {
        return implode(
            ':',
            [
                $observation->type,
                $observation->clientId ?? 'business',
            ]
        );
    }

    private function hasWorsened(
        BusinessObservation $previous,
        BusinessObservation $current
    ): bool {
        if (
            $previous->value !== null
            && $current->value !== null
            && $current->value > $previous->value
        ) {
            return true;
        }

        return $current->priority > $previous->priority;
    }

    private function hasImproved(
        BusinessObservation $previous,
        BusinessObservation $current
    ): bool {
        if (
            $previous->value !== null
            && $current->value !== null
            && $current->value < $previous->value
        ) {
            return true;
        }

        return $current->priority < $previous->priority;
    }
}
