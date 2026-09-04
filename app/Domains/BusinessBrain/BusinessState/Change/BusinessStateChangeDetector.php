<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class BusinessStateChangeDetector
{
    public function compare(
        BusinessStateBaseline $previous,
        BusinessStateBaseline $current,
    ): Collection {
        /*
         * Change requires temporal direction.
         *
         * Comparing the same instant, or comparing backwards,
         * cannot support a defensible "changed from" claim.
         */
        if (
            ! $current->asOf->greaterThan(
                $previous->asOf
            )
        ) {
            throw new InvalidArgumentException(
                'Current business state baseline must be later than the previous baseline.'
            );
        }

        $previousMetrics =
            $previous->keyedMetrics();

        $currentMetrics =
            $current->keyedMetrics();

        /*
         * A changed metric universe is not silently interpreted
         * as a business change.
         *
         * Schema/scope changes and active-client changes require
         * explicit handling rather than manufacturing new/resolved
         * business conditions.
         */
        if (
            $previousMetrics
                ->keys()
                ->values()
                ->all()
            !==
            $currentMetrics
                ->keys()
                ->values()
                ->all()
        ) {
            throw new InvalidArgumentException(
                'Business state baselines are not comparable because their metric sets differ.'
            );
        }

        $changes =
            collect();

        foreach (
            $currentMetrics as $key => $currentMetric
        ) {
            /** @var BusinessStateMetric $previousMetric */
            $previousMetric =
                $previousMetrics->get(
                    $key
                );

            /*
             * Provenance is part of comparability.
             *
             * If the authoritative source behind a metric changes,
             * the values are not silently compared as though they
             * represented the same claim.
             */
            if (
                $previousMetric->source
                !== $currentMetric->source
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Business state metric source changed for %s.',
                        $key
                    )
                );
            }

            $kind =
                $this->changeKind(
                    previous: $previousMetric,
                    current: $currentMetric
                );

            if ($kind === null) {
                continue;
            }

            $changes->push(
                new BusinessStateChange(
                    previous: $previousMetric,
                    current: $currentMetric,
                    kind: $kind,
                    previousAsOf: $previous->asOf,
                    currentAsOf: $current->asOf
                )
            );
        }

        return $changes->values();
    }

    private function changeKind(
        BusinessStateMetric $previous,
        BusinessStateMetric $current,
    ): ?string {
        /*
         * Unknown -> known is evidence becoming sufficient
         * to establish a value.
         *
         * It is NOT equivalent to zero -> value.
         */
        if (
            ! $previous->known
            && $current->known
        ) {
            return BusinessStateChange::BECAME_KNOWN;
        }

        /*
         * Known -> unknown means the stronger claim can no
         * longer safely be made.
         *
         * It is NOT a numerical decrease.
         */
        if (
            $previous->known
            && ! $current->known
        ) {
            return BusinessStateChange::BECAME_UNKNOWN;
        }

        /*
         * Unknown -> unknown carries no comparable value.
         */
        if (
            ! $previous->known
            && ! $current->known
        ) {
            return null;
        }

        if (
            $current->value
            > $previous->value
        ) {
            return BusinessStateChange::INCREASED;
        }

        if (
            $current->value
            < $previous->value
        ) {
            return BusinessStateChange::DECREASED;
        }

        return null;
    }
}
