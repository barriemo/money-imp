<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\AttentionRanker;
use App\Domains\BusinessBrain\Attention\AttentionSignal;
use Tests\TestCase;

class AttentionRankerTest extends TestCase
{
    public function test_attention_signals_are_ranked_by_priority(): void
    {
        $signals =
            collect([
                new AttentionSignal(
                    type: 'allocation_variance',

                    client: 'Client B',

                    priority: 70,

                    value: 500,

                    reason: 'Delivery cost exposure detected.'
                ),

                new AttentionSignal(
                    type: 'recovery',

                    client: 'Walker',

                    priority: 90,

                    value: 2090,

                    reason: 'Commercial work not recovered.'
                ),
            ]);

        $ranked =
            app(
                AttentionRanker::class
            )->rank(
                $signals
            );

        $this->assertSame(
            'Walker',
            $ranked->first()->client
        );

        $this->assertSame(
            'Client B',
            $ranked->last()->client
        );
    }
}
