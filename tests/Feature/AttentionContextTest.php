<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use Tests\TestCase;

class AttentionContextTest extends TestCase
{
    public function test_attention_context_holds_intelligence_inputs(): void
    {
        $context =
            new AttentionContext(
                recovery: 'recovery',

                allocation: 'allocation',

                vat: 'vat'
            );

        $this->assertSame(
            'recovery',
            $context->recovery
        );

        $this->assertSame(
            'allocation',
            $context->allocation
        );

        $this->assertSame(
            'vat',
            $context->vat
        );
    }
}
