<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBrainCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_brain_command_presents_intelligence(): void
    {
        $project = Project::factory()->create([
            'name' => 'Walker CRM',
            'commercial_value' => 150000,
        ]);

        ProjectAction::factory()->create([
            'project_id' => $project->id,
            'action' => 'Improve customer onboarding',
            'priority' => 'high',
        ]);

        $this->artisan(
            'project:brain'
        )
            ->expectsOutputToContain(
                'Project Brain'
            )
            ->expectsOutputToContain(
                'Walker CRM'
            )
            ->expectsOutputToContain(
                'Improve customer onboarding'
            )
            ->assertSuccessful();
    }
}
