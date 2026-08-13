<?php

namespace App\Domains\BusinessBrain\Learning;

class ActionOutcomeProfile
{
    public function __construct(
        public string $type,

        public int $completedCount,

        public int $financialSuccessCount,

        public float $totalFinancialResult,

        public float $averageFinancialResult,

        public int $financialSuccessRate,

        public ?float $averageCompletionHours
    ) {}
}
