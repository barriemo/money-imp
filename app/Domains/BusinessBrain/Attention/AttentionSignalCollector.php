<?php

namespace App\Domains\BusinessBrain\Attention;

use App\Domains\BusinessBrain\Attention\Builders\AllocationAttentionSignalBuilder;
use App\Domains\BusinessBrain\Attention\Builders\RecoveryAttentionSignalBuilder;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use Illuminate\Support\Collection;

class AttentionSignalCollector
{
    public function __construct(
        private RecoveryAttentionSignalBuilder $recoveryBuilder,

        private AllocationAttentionSignalBuilder $allocationBuilder
    ) {}

    public function collect(
        string $client,

        RecoveryOpportunitySummary $recovery,

        AllocationVarianceSummary $allocation
    ): Collection {
        return collect([
            $this->recoveryBuilder->build(
                $recovery
            ),

            $this->allocationBuilder->build(
                $client,
                $allocation
            ),
        ])
            ->filter()
            ->values();
    }
}
