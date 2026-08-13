<?php

namespace App\Domains\BusinessBrain\Learning;

class ClientActionOutcomeProfile
{
    public function __construct(
        public string $clientId,

        public string $client,

        public int $completedCount,

        public int $financialSuccessCount,

        public float $totalFinancialResult,

        public float $averageFinancialResult,

        public int $financialSuccessRate,

        public ?float $averageCompletionHours
    ) {}
}
