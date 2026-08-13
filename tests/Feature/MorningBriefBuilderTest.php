<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\MorningBrief\MorningBriefBuilder;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use Tests\TestCase;

class MorningBriefBuilderTest extends TestCase
{
    public function test_builds_ranked_business_attention_brief(): void
    {
        $brief =
            app(
                MorningBriefBuilder::class
            )->build(
                new AttentionContext(
                    client: 'Walker',

                    recovery: new RecoveryOpportunitySummary(
                        clientId: 'Walker',

                        opportunityCount: 2,

                        totalValue: 5000,

                        highestValue: 3000,

                        confidence: 90
                    ),

                    allocation: new AllocationVarianceSummary(
                        totalOverrunHours: 10,

                        totalCostExposure: 1000,

                        highestRiskResource: 'John',

                        attentionRequired: true
                    )
                )
            );

        $this->assertCount(
            2,
            $brief->signals
        );

        $this->assertSame(
            'recovery',
            $brief->signals->first()->type
        );

        $this->assertSame(
            'allocation_variance',
            $brief->signals->last()->type
        );
    }
}
