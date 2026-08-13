<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Learning\LearningScoreModifier;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningScoreModifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_does_not_modify_score_without_enough_evidence(): void
    {
        for ($i = 0; $i < 4; $i++) {
            ExecutiveAction::create([
                'fingerprint' => hash(
                    'sha256',
                    'small-sample-'.$i
                ),

                'type' => 'financial_opportunity',

                'title' => 'Recover revenue',

                'description' => 'Revenue overdue.',

                'recommended_action' => 'Chase client.',

                'confidence' => 100,

                'urgency' => 90,

                'score' => 90,

                'status' => 'completed',

                'financial_result' => 1000,
            ]);
        }

        $modifier =
            app(
                LearningScoreModifier::class
            )->forType(
                'financial_opportunity'
            );

        $this->assertSame(
            0,
            $modifier
        );
    }

    public function test_learning_can_positively_modify_score_with_sufficient_history(): void
    {
        for ($i = 0; $i < 10; $i++) {
            ExecutiveAction::create([
                'fingerprint' => hash(
                    'sha256',
                    'successful-history-'.$i
                ),

                'type' => 'financial_opportunity',

                'title' => 'Recover revenue',

                'description' => 'Revenue overdue.',

                'recommended_action' => 'Chase client.',

                'confidence' => 100,

                'urgency' => 90,

                'score' => 90,

                'status' => 'completed',

                'financial_result' => 1000,
            ]);
        }

        $modifier =
            app(
                LearningScoreModifier::class
            )->forType(
                'financial_opportunity'
            );

        $this->assertGreaterThan(
            0,
            $modifier
        );

        $this->assertLessThanOrEqual(
            10,
            $modifier
        );
    }
}
