<?php

namespace Tests\Feature;

use App\Domains\EvidenceAcquisition\EvidenceQuestion;
use App\Domains\EvidenceAcquisition\Ranking\EvidenceQueueBuilder;
use Tests\TestCase;

class EvidenceQueueBuilderTest extends TestCase
{
    public function test_high_value_unknown_is_ranked_first(): void
    {
        $questions = collect([
            new EvidenceQuestion(
                question: 'Which server hosts this client?',

                reason: 'Hosting relationship is unknown.',

                priority: 0,

                domain: 'infrastructure',

                evidence: [
                    'impact' => 90,

                    'confidence' => 0,

                    'financial_value' => 150,

                    'urgency' => 60,
                ]
            ),

            new EvidenceQuestion(
                question: 'Confirm small licence cost.',

                reason: 'Low value unknown.',

                priority: 0,

                domain: 'commercial',

                evidence: [
                    'impact' => 20,

                    'confidence' => 70,

                    'financial_value' => 50,

                    'urgency' => 10,
                ]
            ),
        ]);

        $queue =
            app(
                EvidenceQueueBuilder::class
            )
                ->build(
                    $questions
                );

        $this->assertSame(
            'infrastructure',
            $queue->first()->domain
        );

        $this->assertGreaterThan(
            $queue->last()->priority,
            $queue->first()->priority
        );
    }
}
