<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Builders\AllocationAttentionSignalBuilder;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use Tests\TestCase;

class AllocationAttentionSignalBuilderTest extends TestCase
{
    public function test_allocation_variance_creates_attention_signal(): void
    {
        $signal =
            app(
                AllocationAttentionSignalBuilder::class
            )->build(
                'Client B',

                new AllocationVarianceSummary(
                    totalOverrunHours: 25,

                    totalCostExposure: 1625,

                    highestRiskResource: 'John Smith',

                    attentionRequired: true
                )
            );

        $this->assertSame(
            'allocation_variance',
            $signal->type
        );

        $this->assertSame(
            'Client B',
            $signal->client
        );

        $this->assertSame(
            1625.0,
            $signal->value
        );

        $this->assertSame(
            16,
            $signal->priority
        );
    }

    public function test_no_cost_exposure_creates_no_signal(): void
    {
        $signal =
            app(
                AllocationAttentionSignalBuilder::class
            )->build(
                'Client B',

                new AllocationVarianceSummary(
                    totalOverrunHours: 0,

                    totalCostExposure: 0,

                    highestRiskResource: 'John Smith',

                    attentionRequired: false
                )
            );

        $this->assertNull(
            $signal
        );
    }
}
