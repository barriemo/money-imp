<?php

namespace App\Domains\BusinessBrain\Attention\Builders;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;

class AllocationAttentionSignalBuilder
{
    public function build(
        string $client,
        AllocationVarianceSummary $summary
    ): ?AttentionSignal {
        if (
            $summary->totalCostExposure <= 0
        ) {
            return null;
        }

        return new AttentionSignal(
            type: 'allocation_variance',

            client: $client,

            priority: $this->priority(
                $summary->totalCostExposure
            ),

            value: $summary->totalCostExposure,

            reason: 'Resource allocation variance detected.'
        );
    }

    private function priority(
        float $value
    ): int {
        return min(
            100,
            (int) round(
                $value / 100
            )
        );
    }
}
