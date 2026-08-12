<?php

namespace App\Domains\BusinessBrain\CommercialBrief;

use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummariser;
use App\Models\Client;

class CommercialBriefBuilder
{
    public function __construct(
        private RecoveryOpportunitySummariser $summariser,
    ) {}

    public function build(
        Client $client
    ): CommercialBrief {
        $summary =
            $this->summariser->summarise(
                $client
            );

        $health =
            $summary->totalValue > 0
                ? 'attention_required'
                : 'healthy';

        return new CommercialBrief(
            health: $health,

            recoveryValue: $summary->totalValue,

            recoveryCount: $summary->opportunityCount,

            largestOpportunity: $summary->highestValue,

            confidence: $summary->confidence,

            recommendations: $summary->totalValue > 0
                ? [
                    'Review outstanding commercial recovery opportunities.',
                ]
                : []
        );
    }
}
