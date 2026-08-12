<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Attention\Providers\RecoveryAttentionProvider;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use Tests\TestCase;

class RecoveryAttentionProviderTest extends TestCase
{
    public function test_recovery_provider_returns_attention_signal(): void
    {
        $signals =
            app(
                RecoveryAttentionProvider::class
            )->provide(
                new AttentionContext(
                    recovery: new RecoveryOpportunitySummary(
                        clientId: 'Walker',

                        opportunityCount: 3,

                        totalValue: 2090,

                        highestValue: 1000,

                        confidence: 90
                    )
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
            2090.0,
            $signals->first()->value
        );
    }

    public function test_missing_recovery_creates_no_signals(): void
    {
        $signals =
            app(
                RecoveryAttentionProvider::class
            )->provide(
                new AttentionContext
            );

        $this->assertCount(
            0,
            $signals
        );
    }
}
