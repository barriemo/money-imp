<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Services\ProjectActionService;
use App\Models\Project;
use Illuminate\Console\Command;

class ProjectActionSyncCommand extends Command
{
    protected $signature = 'project:sync-actions';

    protected $description = 'Create Project Imp actions from current recommendations';

    public function handle(
        ProjectActionService $actions
    ): int {
        $created = 0;

        $projects =
            Project::query()
                ->where(
                    'status',
                    'active'
                )
                ->get();

        foreach ($projects as $project) {
            $created += count(
                $actions->createFromRecommendations(
                    $project
                )
            );
        }

        $this->line(
            'MONEY IMP'
        );

        $this->line(
            'Project Action Sync'
        );

        $this->newLine();

        $this->line(
            'Actions created: '.$created
        );

        return self::SUCCESS;
    }
}
