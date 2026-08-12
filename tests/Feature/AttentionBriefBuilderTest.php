<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\BusinessBrain\Attention\Brief\AttentionBriefBuilder;
use Tests\TestCase;

class AttentionBriefBuilderTest extends TestCase
{
    public function test_builds_ranked_attention_brief(): void
    {
        $brief =
            app(
                AttentionBriefBuilder::class
            )->build(
                collect([
                    new AttentionSignal(
                        type: 'recovery',

                        client: 'Walker',

                        priority: 90,

                        value: 2090,

                        reason: 'Recovery required.'
                    ),

                    new AttentionSignal(
                        type: 'allocation_variance',

                        client: 'Client B',

                        priority: 70,

                        value: 500,

                        reason: 'Allocation issue.'
                    ),
                ])
            );

        $this->assertSame(
            2,
            $brief->totalSignals
        );

        $this->assertSame(
            90,
            $brief->highestPriority
        );

        $this->assertSame(
            'Walker',
            $brief->signals
                ->first()
                ->client
        );
    }

    public function test_empty_signals_create_empty_brief(): void
    {
        $brief =
            app(
                AttentionBriefBuilder::class
            )->build(
                collect()
            );

        $this->assertSame(
            0,
            $brief->totalSignals
        );

        $this->assertSame(
            0,
            $brief->highestPriority
        );
    }
}
