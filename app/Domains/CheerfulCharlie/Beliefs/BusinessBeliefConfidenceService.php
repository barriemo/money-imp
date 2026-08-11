<?php

namespace App\Domains\CheerfulCharlie\Beliefs;

use App\Models\BusinessBelief;

class BusinessBeliefConfidenceService
{
    public function calculate(
        BusinessBelief $belief
    ): int {
        $belief->loadMissing(
            'evidence'
        );

        if ($belief->evidence->isEmpty()) {
            return $belief->confidence;
        }

        $support = 0.0;
        $contradict = 0.0;

        foreach ($belief->evidence as $evidence) {
            $score =
                (
                    $evidence->weight
                    * $evidence->confidence
                ) / 100;

            if (
                $evidence->relationship
                === 'contradicts'
            ) {
                $contradict += $score;

                continue;
            }

            $support += $score;
        }

        if (
            $support <= 0
            && $contradict <= 0
        ) {
            return 50;
        }

        $ratio =
            $support
            / max(
                1,
                $support + $contradict
            );

        return (int) round(
            max(
                0,
                min(
                    100,
                    $ratio * 100
                )
            )
        );
    }
}
