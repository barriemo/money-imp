<?php

namespace App\Domains\BusinessBrain\Attention\Builders;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;

class RecoveryAttentionSignalBuilder
{
    public function build(
        RecoveryOpportunitySummary $summary
    ): ?AttentionSignal {
        if (
            $summary->totalValue <= 0
        ) {
            return null;
        }

        return new AttentionSignal(
            type: 'recovery',

            client: $summary->clientId,

            priority: $this->priority(
                $summary->totalValue
            ),

            value: $summary->totalValue,

            reason: 'Commercial work recorded without invoice recovery.'
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
