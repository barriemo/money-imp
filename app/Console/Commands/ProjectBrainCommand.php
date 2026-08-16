<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionIntelligencePresenter;
use App\Models\ProjectAction;
use Illuminate\Console\Command;

class ProjectBrainCommand extends Command
{
    protected $signature = 'project:brain';

    protected $description = 'Show Project Imp business intelligence';

    public function handle(
        ProjectActionIntelligencePresenter $presenter
    ): int {
        $actions = ProjectAction::query()
            ->with([
                'project',
                'evidence',
                'events',
                'outcomes',
            ])
            ->get();

        $this->line('MONEY IMP');
        $this->line('Project Brain');
        $this->newLine();

        foreach ($actions as $action) {
            $data = $presenter->present($action);

            $this->line(
                $action->project->name
            );

            $this->line(
                'Priority: '.$data['priority']['category']
                .' ('.$data['priority']['score'].')'
            );

            $this->line(
                'Action: '.$data['action']['action']
            );

            if (! empty($data['assessment'])) {
                $this->line(
                    'Assessment: '
                    .$data['assessment'][0]['result']
                );
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
