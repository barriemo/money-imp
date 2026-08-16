<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionIntelligencePresenter;
use App\Models\ProjectAction;
use App\Models\ProjectActionRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectActionIntelligenceRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_presenter_includes_recommendations(): void
    {
        $action = ProjectAction::factory()->create();

        ProjectActionRecommendation::factory()->create([
            'project_action_id' => $action->id,
            'recommendation' => 'Review onboarding journey',
            'confidence' => 85,
        ]);

        $data = app(
            ProjectActionIntelligencePresenter::class
        )->present(
            $action->load(
                'recommendations'
            )
        );

        $this->assertCount(
            1,
            $data['recommendations']
        );

        $this->assertSame(
            'Review onboarding journey',
            $data['recommendations'][0]['recommendation']
        );

        $this->assertSame(
            85,
            $data['recommendations'][0]['confidence']
        );
    }
}
