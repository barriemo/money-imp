<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\AttentionSignalCollector;
use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use Tests\TestCase;

class AttentionSignalCollectorTest extends TestCase
{
    public function test_collects_multiple_intelligence_signals(): void
    {
        $signals =
            app(
                AttentionSignalCollector::class
            )->collect(
                new AttentionContext(
                    client: 'Walker',

                    recovery: new RecoveryOpportunitySummary(
                        clientId: 'Walker',

                        opportunityCount: 3,

                        totalValue: 2090,

                        highestValue: 1000,

                        confidence: 90
                    ),

                    allocation: new AllocationVarianceSummary(
                        totalOverrunHours: 25,

                        totalCostExposure: 1625,

                        highestRiskResource: 'John Smith',

                        attentionRequired: true
                    )
                )
            );

        $this->assertCount(
            2,
            $signals
        );

        $this->assertSame(
            'recovery',
            $signals->first()->type
        );

        $this->assertSame(
            'allocation_variance',
            $signals->last()->type
        );

        $this->assertSame(
            'Walker',
            $signals->last()->client
        );
    }
}
