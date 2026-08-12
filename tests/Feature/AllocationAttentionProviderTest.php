<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Attention\Providers\AllocationAttentionProvider;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use Tests\TestCase;

class AllocationAttentionProviderTest extends TestCase
{
    public function test_allocation_provider_returns_attention_signal(): void
    {
        $signals =
            app(
                AllocationAttentionProvider::class
            )->provide(
                new AttentionContext(
                    client: 'Walker',

                    allocation: new AllocationVarianceSummary(
                        totalOverrunHours: 25,

                        totalCostExposure: 1625,

                        highestRiskResource: 'John Smith',

                        attentionRequired: true
                    )
                )
            );

        $this->assertCount(
            1,
            $signals
        );

        $this->assertSame(
            'allocation_variance',
            $signals->first()->type
        );

        $this->assertSame(
            1625.0,
            $signals->first()->value
        );
    }

    public function test_missing_allocation_creates_no_signals(): void
    {
        $signals =
            app(
                AllocationAttentionProvider::class
            )->provide(
                new AttentionContext
            );

        $this->assertCount(
            0,
            $signals
        );
    }
}
