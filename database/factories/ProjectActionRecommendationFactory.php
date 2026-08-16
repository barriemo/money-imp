<?php

namespace Database\Factories;

use App\Models\ProjectAction;
use App\Models\ProjectActionRecommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActionRecommendation>
 */
class ProjectActionRecommendationFactory extends Factory
{
    protected $model = ProjectActionRecommendation::class;

    public function definition(): array
    {
        return [
            'project_action_id' => ProjectAction::factory(),
            'type' => 'next_step',
            'recommendation' => fake()->sentence(),
            'expected_impact' => 'Improve conversion',
            'confidence' => 85,
            'status' => 'open',
        ];
    }
}
