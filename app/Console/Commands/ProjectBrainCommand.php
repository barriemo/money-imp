<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Presenters\ProjectBrainPresenter;
use App\Models\ProjectAction;
use Illuminate\Console\Command;

class ProjectBrainCommand extends Command
{
    protected $signature = 'project:brain';

    protected $description = 'Show Project Imp business intelligence';

    public function handle(
        ProjectBrainPresenter $presenter
    ): int {
        $actions = ProjectAction::query()
            ->with([
                'project',
                'evidence',
                'events',
                'outcomes',
            ])
            ->get();

        $brain = $presenter->present($actions);

        $this->line('MONEY IMP');
        $this->line('Project Brain');
        $this->newLine();

        foreach ([
            'urgent',
            'important',
            'normal',
        ] as $category) {
            if (empty($brain[$category])) {
                continue;
            }

            $this->line(
                strtoupper($category)
            );

            $this->newLine();

            foreach ($brain[$category] as $item) {
                $this->line(
                    $item['action']['project']
                );

                $this->line(
                    $item['action']['action']
                );

                $this->line(
                    'Priority: '
                    .$item['priority']['category']
                    .' ('
                    .$item['priority']['score']
                    .')'
                );

                $this->newLine();
            }
        }

        return self::SUCCESS;
    }
}
