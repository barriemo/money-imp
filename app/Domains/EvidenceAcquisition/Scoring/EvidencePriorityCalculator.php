<?php

namespace App\Domains\EvidenceAcquisition\Scoring;

class EvidencePriorityCalculator
{
    public function calculate(
        int $impact,
        int $confidence,
        float $financialValue = 0,
        int $urgency = 0
    ): int {
        $confidenceGap =
            100 - $confidence;

        $financialScore =
            match (true) {
                $financialValue >= 100000 => 100,
                $financialValue >= 10000 => 75,
                $financialValue >= 1000 => 50,
                $financialValue > 0 => 20,
                default => 0,
            };

        return min(
            100,
            (int) round(
                (
                    ($impact * 0.35)
                    +
                    ($confidenceGap * 0.35)
                    +
                    ($financialScore * 0.2)
                    +
                    ($urgency * 0.1)
                )
            )
        );
    }
}
