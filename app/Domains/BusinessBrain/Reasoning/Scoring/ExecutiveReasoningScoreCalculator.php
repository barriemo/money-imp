<?php

namespace App\Domains\BusinessBrain\Reasoning\Scoring;

class ExecutiveReasoningScoreCalculator
{
    public function calculate(
        ?float $financialImpact,
        int $urgency,
        int $confidence,
        ?int $effortMinutes
    ): int {
        $financialScore =
            $this->financialScore(
                $financialImpact
            );

        $effortPenalty =
            $this->effortPenalty(
                $effortMinutes
            );

        $score =
            ($financialScore * 0.45)
            + ($urgency * 0.30)
            + ($confidence * 0.25)
            - $effortPenalty;

        return max(
            0,
            min(
                100,
                (int) round(
                    $score
                )
            )
        );
    }

    private function financialScore(
        ?float $value
    ): int {
        if ($value === null || $value <= 0) {
            return 25;
        }

        return match (true) {
            $value >= 10000 => 100,
            $value >= 5000 => 90,
            $value >= 2500 => 80,
            $value >= 1000 => 65,
            $value >= 500 => 50,
            default => 35,
        };
    }

    private function effortPenalty(
        ?int $minutes
    ): int {
        if ($minutes === null) {
            return 5;
        }

        return match (true) {
            $minutes <= 10 => 0,
            $minutes <= 30 => 3,
            $minutes <= 60 => 7,
            $minutes <= 120 => 12,
            default => 20,
        };
    }
}
