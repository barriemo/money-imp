<?php

namespace App\Domains\RevenueTruth;

class RevenueTruthService
{
    public function __construct(
        private RevenueRecoveryEngine $recovery
    ) {}

    public function summary(): array
    {
        $recommendations =
            $this->recovery
                ->recoverable();

        $monthly =
            round(
                (float)
                $recommendations->sum(
                    'estimated_monthly_value'
                ),
                2
            );

        return [
            'recommendations' => $recommendations,

            'recommendation_count' => $recommendations->count(),

            'client_count' => $recommendations
                ->pluck(
                    'client_id'
                )
                ->unique()
                ->count(),

            'recoverable_monthly' => $monthly,

            'recoverable_annual' => round(
                $monthly * 12,
                2
            ),

            'missing_recovery_count' => $recommendations
                ->where(
                    'type',
                    'missing_recovery'
                )
                ->count(),

            'under_recovery_count' => $recommendations
                ->where(
                    'type',
                    'under_recovery'
                )
                ->count(),

            'highest_priority' => $recommendations
                ->sortByDesc(
                    'priority'
                )
                ->first(),
        ];
    }
}
