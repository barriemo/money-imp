<?php

namespace Tests\Feature;

use App\Domains\Reasoning\Question;
use App\Domains\Reasoning\Strategies\RevenueRecoveryReasoner;
use Tests\TestCase;

class RevenueRecoveryReasonerTest extends TestCase
{
    public function test_empty_graph_does_not_invent_revenue_recovery(): void
    {
        $answer = app(
            RevenueRecoveryReasoner::class
        )->answer(
            [
                'nodes' => collect(),

                'edges' => collect(),
            ],
            Question::revenueRecovery()
        );

        $this->assertSame(
            0,
            $answer->data[
                'recommendation_count'
            ]
        );

        $this->assertSame(
            0.0,
            $answer->data[
                'monthly_value'
            ]
        );

        $this->assertCount(
            0,
            $answer->evidence
        );

        $this->assertSame(
            100,
            $answer->confidence
        );
    }
}
