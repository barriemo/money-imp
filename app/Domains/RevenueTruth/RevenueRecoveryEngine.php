<?php

namespace App\Domains\RevenueTruth;

use Illuminate\Support\Collection;

class RevenueRecoveryEngine
{
    public function __construct(
        private RevenueRecommendationEngine $recommendations
    ) {}

    public function recoverable(): Collection
    {
        return $this->recommendations
            ->recommendations()
            ->whereIn(
                'type',
                [
                    'missing_recovery',
                    'under_recovery',
                ]
            )
            ->values();
    }
}
