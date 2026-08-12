<?php

namespace Tests\Feature;

use App\Domains\Reasoning\Question;
use App\Domains\Reasoning\Reasoner;
use App\Domains\TruthGraph\TruthGraphNode;
use Tests\TestCase;

class ReasonerTest extends TestCase
{
    public function test_reasoner_answers_supported_question_from_graph(): void
    {
        $graph = [
            'nodes' => collect([
                new TruthGraphNode(
                    type: 'revenue_recommendation',

                    id: 'recommendation-1',

                    label: 'Recover hosting billing',

                    attributes: [
                        'estimated_monthly_value' => 100,

                        'estimated_annual_value' => 1200,
                    ],

                    confidence: 95
                ),
            ]),

            'edges' => collect(),
        ];

        $answer = app(
            Reasoner::class
        )->answer(
            $graph,
            Question::revenueRecovery()
        );

        $this->assertSame(
            'revenue_recovery',
            $answer->questionType
        );

        $this->assertSame(
            100.0,
            $answer->data[
                'monthly_value'
            ]
        );

        $this->assertSame(
            1200.0,
            $answer->data[
                'annual_value'
            ]
        );

        $this->assertCount(
            1,
            $answer->evidence
        );
    }
}
