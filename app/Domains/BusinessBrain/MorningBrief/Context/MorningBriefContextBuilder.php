<?php

namespace App\Domains\BusinessBrain\MorningBrief\Context;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummariser;
use App\Domains\ResourceIntelligence\Allocation\Providers\ClientAllocationVarianceProvider;
use App\Models\Client;

class MorningBriefContextBuilder
{
    public function __construct(
        private RecoveryOpportunitySummariser $recoverySummariser,

        private ClientAllocationVarianceProvider $allocationProvider
    ) {}

    public function build(
        Client $client
    ): AttentionContext {
        return new AttentionContext(
            client: $client->name,

            recovery: $this->recoverySummariser->summarise(
                $client
            ),

            allocation: $this->allocationProvider->provide(
                (string) $client->id
            )
        );
    }
}
