<?php

namespace Database\Factories;

use App\Models\ProjectAction;
use App\Models\ProjectActionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActionEvent>
 */
class ProjectActionEventFactory extends Factory
{
    protected $model = ProjectActionEvent::class;

    public function definition(): array
    {
        return [
            'project_action_id' => ProjectAction::factory(),
            'type' => fake()->randomElement([
                'created',
                'approved',
                'assigned',
                'started',
                'completed',
                'verified',
            ]),
            'payload' => [],
        ];
    }
}
