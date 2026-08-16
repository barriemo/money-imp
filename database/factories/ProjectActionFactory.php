<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAction>
 */
class ProjectActionFactory extends Factory
{
    protected $model = ProjectAction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'action' => fake()->sentence(),
            'priority' => 'medium',
            'status' => ProjectAction::STATUS_OPEN,
            'reason' => fake()->sentence(),
            'assigned_to' => null,
            'owner_type' => null,
            'completed_at' => null,
            'verified_at' => null,
        ];
    }
}
