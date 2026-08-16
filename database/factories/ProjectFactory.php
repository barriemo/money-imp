<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => 'active',
            'health' => 'healthy',
            'owner' => fake()->name(),
            'commercial_value' => 10000,
            'billing_model' => 'fixed',
            'start_date' => now()->subWeek(),
            'target_date' => now()->addMonth(),
        ];
    }
}
