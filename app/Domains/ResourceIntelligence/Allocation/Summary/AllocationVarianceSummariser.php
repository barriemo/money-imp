<?php

namespace App\Domains\ResourceIntelligence\Allocation\Summary;

use App\Domains\ResourceIntelligence\Allocation\AllocationVariance;
use Illuminate\Support\Collection;

class AllocationVarianceSummariser
{
    public function summarise(
        Collection $variances
    ): AllocationVarianceSummary {
        $overruns =
            $variances->filter(
                fn (AllocationVariance $variance) => $variance->requiresAttention()
            );

        $highest =
            $overruns
                ->sortByDesc(
                    'costVariance'
                )
                ->first();

        return new AllocationVarianceSummary(
            totalOverrunHours: (int) $overruns->sum(
                fn (AllocationVariance $variance) => $variance->hoursVariance()
            ),

            totalCostExposure: (float) $overruns->sum(
                'costVariance'
            ),

            highestRiskResource: $highest?->resource,

            attentionRequired: $overruns->isNotEmpty()
        );
    }
}
