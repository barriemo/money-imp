<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Builders\RecoveryAttentionSignalBuilder;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use Tests\TestCase;

class RecoveryAttentionSignalBuilderTest extends TestCase
{
    public function test_recovery_summary_creates_attention_signal(): void
    {
        $signal =
            app(
                RecoveryAttentionSignalBuilder::class
            )->build(
                new RecoveryOpportunitySummary(
                    clientId: 'Walker',

                    opportunityCount: 3,

                    totalValue: 2090,

                    highestValue: 950,

                    confidence: 90
                )
            );

        $this->assertSame(
            'recovery',
            $signal->type
        );

        $this->assertSame(
            'Walker',
            $signal->client
        );

        $this->assertSame(
            2090.0,
            $signal->value
        );

        $this->assertSame(
            21,
            $signal->priority
        );
    }

    public function test_empty_recovery_creates_no_signal(): void
    {
        $signal =
            app(
                RecoveryAttentionSignalBuilder::class
            )->build(
                new RecoveryOpportunitySummary(
                    clientId: 'Walker',

                    opportunityCount: 0,

                    totalValue: 0,

                    highestValue: 0,

                    confidence: 0
                )
            );

        $this->assertNull(
            $signal
        );
    }
}
