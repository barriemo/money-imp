<?php

namespace App\Domains\Attribution;

use Illuminate\Support\Collection;

class AttributionConfidence
{
    public function calculate(
        Collection $evidence
    ): int {
        if ($evidence->isEmpty()) {
            return 0;
        }

        $scores = $evidence
            ->pluck('confidence')
            ->map(
                fn ($value) => max(
                    0,
                    min(
                        100,
                        (int) $value
                    )
                )
            )
            ->sortDesc()
            ->values();

        $confidence =
            (int) $scores->first();

        /*
         * Independent corroborating evidence should strengthen
         * the hypothesis without manufacturing certainty.
         */
        foreach (
            $scores->skip(1) as $score
        ) {
            $confidence +=
                (int) round(
                    (100 - $confidence)
                    * ($score / 100)
                    * 0.25
                );
        }

        return min(
            99,
            $confidence
        );
    }
}
