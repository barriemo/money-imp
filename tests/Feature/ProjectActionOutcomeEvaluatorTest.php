<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Services\ProjectActionOutcomeEvaluator;
use App\Models\ProjectActionOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionOutcomeEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_outcome_is_identified(): void
    {
        $outcome = ProjectActionOutcome::factory()->create([
            'value' => '+14%',
            'confidence' => 90,
        ]);

        $assessment = app(
            ProjectActionOutcomeEvaluator::class
        )->evaluate($outcome);

        $this->assertSame(
            'success',
            $assessment['result']
        );
    }

    public function test_low_confidence_outcome_is_uncertain(): void
    {
        $outcome = ProjectActionOutcome::factory()->create([
            'value' => '+14%',
            'confidence' => 40,
        ]);

        $assessment = app(
            ProjectActionOutcomeEvaluator::class
        )->evaluate($outcome);

        $this->assertSame(
            'uncertain',
            $assessment['result']
        );
    }

    public function test_unknown_outcome_returns_unknown(): void
    {
        $outcome = ProjectActionOutcome::factory()->create([
            'value' => null,
            'confidence' => 80,
        ]);

        $assessment = app(
            ProjectActionOutcomeEvaluator::class
        )->evaluate($outcome);

        $this->assertSame(
            'unknown',
            $assessment['result']
        );
    }
}
