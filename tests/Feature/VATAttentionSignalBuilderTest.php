<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Builders\VATAttentionSignalBuilder;
use App\Domains\VATIntelligence\VATExposure;
use Tests\TestCase;

class VATAttentionSignalBuilderTest extends TestCase
{
    public function test_vat_exposure_creates_attention_signal(): void
    {
        $signal =
            app(
                VATAttentionSignalBuilder::class
            )->build(
                'Purple Imp',

                new VATExposure(
                    liability: 30000,

                    priority: 100,

                    reason: 'VAT liability requires cash planning.'
                )
            );

        $this->assertSame(
            'vat_exposure',
            $signal->type
        );

        $this->assertSame(
            'Purple Imp',
            $signal->client
        );

        $this->assertSame(
            30000.0,
            $signal->value
        );

        $this->assertSame(
            100,
            $signal->priority
        );
    }

    public function test_zero_vat_liability_creates_no_signal(): void
    {
        $signal =
            app(
                VATAttentionSignalBuilder::class
            )->build(
                'Purple Imp',

                new VATExposure(
                    liability: 0,

                    priority: 0,

                    reason: 'No VAT exposure.'
                )
            );

        $this->assertNull(
            $signal
        );
    }
}
