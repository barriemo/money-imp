<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\AttentionSignalBuilder;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use Tests\TestCase;

class AttentionSignalBuilderTest extends TestCase
{
    public function test_builds_attention_signals_from_domain_summaries(): void
    {
        $signals =
            app(
                AttentionSignalBuilder::class
            )->build(
                new RecoveryOpportunitySummary(
                    clientId: 'Walker',

                    opportunityCount: 3,

                    totalValue: 2090,

                    highestValue: 950,

                    confidence: 90
                )
            );

        $this->assertCount(
            1,
            $signals
        );

        $this->assertSame(
            'recovery',
            $signals->first()->type
        );

        $this->assertSame(
            'Walker',
            $signals->first()->client
        );
    }

    public function test_no_recovery_creates_no_attention_signals(): void
    {
        $signals =
            app(
                AttentionSignalBuilder::class
            )->build(
                new RecoveryOpportunitySummary(
                    clientId: 'Walker',

                    opportunityCount: 0,

                    totalValue: 0,

                    highestValue: 0,

                    confidence: 0
                )
            );

        $this->assertCount(
            0,
            $signals
        );
    }
}
