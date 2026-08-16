<?php

namespace Database\Factories;

use App\Models\ProjectAction;
use App\Models\ProjectActionOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActionOutcome>
 */
class ProjectActionOutcomeFactory extends Factory
{
    protected $model = ProjectActionOutcome::class;

    public function definition(): array
    {
        return [
            'project_action_id' => ProjectAction::factory(),
            'type' => 'improvement',
            'description' => fake()->sentence(),
            'metric' => 'conversion_rate',
            'value' => '+14%',
            'confidence' => 90,
            'metadata' => [],
        ];
    }
}
