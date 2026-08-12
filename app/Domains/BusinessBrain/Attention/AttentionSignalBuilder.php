<?php

namespace App\Domains\BusinessBrain\Attention;

use App\Domains\BusinessBrain\Attention\Builders\RecoveryAttentionSignalBuilder;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use Illuminate\Support\Collection;

class AttentionSignalBuilder
{
    public function __construct(
        private RecoveryAttentionSignalBuilder $recovery
    ) {}

    public function build(
        RecoveryOpportunitySummary $summary
    ): Collection {
        return collect([
            $this->recovery->build(
                $summary
            ),
        ])
            ->filter()
            ->values();
    }
}
