<?php

namespace Database\Factories;

use App\Models\ProjectAction;
use App\Models\ProjectActionEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActionEvidence>
 */
class ProjectActionEvidenceFactory extends Factory
{
    protected $model = ProjectActionEvidence::class;

    public function definition(): array
    {
        return [
            'project_action_id' => ProjectAction::factory(),
            'type' => fake()->randomElement([
                'observation',
                'metric',
                'recommendation',
                'risk',
            ]),
            'description' => fake()->sentence(),
            'source' => fake()->word(),
            'confidence' => fake()->numberBetween(50, 100),
            'metadata' => [],
        ];
    }
}
