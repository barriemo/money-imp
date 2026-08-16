<?php

namespace Database\Factories;

use App\Models\ProjectAction;
use App\Models\ProjectActionCommitment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActionCommitment>
 */
class ProjectActionCommitmentFactory extends Factory
{
    protected $model = ProjectActionCommitment::class;

    public function definition(): array
    {
        return [
            'project_action_id' => ProjectAction::factory(),
            'owner' => fake()->name(),
            'status' => 'committed',
            'due_date' => now()->addDays(7),
            'committed_at' => now(),
            'completed_at' => null,
        ];
    }
}
