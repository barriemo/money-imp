<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionOutcomePresenter;
use App\Models\ProjectActionOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionOutcomePresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_returns_outcome_details(): void
    {
        $outcome = ProjectActionOutcome::factory()->create([
            'type' => 'improvement',
            'description' => 'Registration conversion improved.',
            'metric' => 'conversion_rate',
            'value' => '+14%',
            'confidence' => 90,
        ]);

        $data = app(
            ProjectActionOutcomePresenter::class
        )->present($outcome);

        $this->assertSame(
            'improvement',
            $data['type']
        );

        $this->assertSame(
            '+14%',
            $data['value']
        );
    }

    public function test_presenter_includes_confidence(): void
    {
        $outcome = ProjectActionOutcome::factory()->create([
            'confidence' => 95,
        ]);

        $data = app(
            ProjectActionOutcomePresenter::class
        )->present($outcome);

        $this->assertSame(
            95,
            $data['confidence']
        );
    }
}
