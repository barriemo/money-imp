<?php

namespace App\Domains\CheerfulCharlie\Review;

class CharlieFindingPriority
{
    public function score(
        string $severity,
        int $confidence,
        ?float $monthlyValue = null
    ): int {
        $severityScore =
            match ($severity) {
                'critical' => 100,
                'high' => 85,
                'medium' => 65,
                'low' => 40,
                default => 50,
            };

        $valueScore =
            match (true) {
                $monthlyValue === null => 50,
                $monthlyValue >= 1000 => 100,
                $monthlyValue >= 500 => 90,
                $monthlyValue >= 250 => 80,
                $monthlyValue >= 100 => 70,
                $monthlyValue >= 50 => 60,
                default => 40,
            };

        return (int) round(
            ($severityScore * 0.45)
            + ($confidence * 0.35)
            + ($valueScore * 0.20)
        );
    }
}
