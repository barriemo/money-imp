<?php

namespace Database\Factories;

use App\Models\ProjectAction;
use App\Models\ProjectActionLearning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActionLearning>
 */
class ProjectActionLearningFactory extends Factory
{
    protected $model = ProjectActionLearning::class;

    public function definition(): array
    {
        return [
            'project_action_id' => ProjectAction::factory(),
            'type' => 'success',
            'summary' => fake()->sentence(),
            'impact' => 'Improved conversion by 14%',
            'confidence' => 90,
            'learned_at' => now(),
        ];
    }
}
