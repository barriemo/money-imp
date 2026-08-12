<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Builders\VATAttentionProvider;
use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\VATIntelligence\VATExposure;
use Tests\TestCase;

class VATAttentionProviderTest extends TestCase
{
    public function test_vat_provider_returns_attention_signal(): void
    {
        $signals =
            app(
                VATAttentionProvider::class
            )->provide(
                new AttentionContext(
                    vat: new VATExposure(
                        liability: 30000,

                        priority: 100,

                        reason: 'VAT liability requires cash planning.'
                    )
                )
            );

        $this->assertCount(
            1,
            $signals
        );

        $this->assertSame(
            'vat_exposure',
            $signals->first()->type
        );
    }

    public function test_missing_vat_creates_no_signals(): void
    {
        $signals =
            app(
                VATAttentionProvider::class
            )->provide(
                new AttentionContext
            );

        $this->assertCount(
            0,
            $signals
        );
    }
}
