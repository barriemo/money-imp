<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionPresenter;
use App\Domains\BusinessBrain\Project\Presenters\ProjectActionTimelinePresenter;
use App\Models\ProjectAction;
use Illuminate\Console\Command;

class ProjectActionsCommand extends Command
{
    protected $signature = 'project:actions';

    protected $description = 'Show Project Imp action queue';

    public function handle(
        ProjectActionPresenter $presenter,
        ProjectActionTimelinePresenter $timelinePresenter
    ): int {
        $actions =
            ProjectAction::query()
                ->with([
                    'project',
                    'evidence',
                    'events',
                    'outcomes',
                ])
                ->where(
                    'status',
                    ProjectAction::STATUS_OPEN
                )
                ->orderByRaw(
                    "CASE priority
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                        ELSE 5
                    END"
                )
                ->get();

        $this->line('MONEY IMP');

        $this->line('Project Imp Action Queue');

        $this->newLine();

        $this->line(
            'Open actions: '.$actions->count()
        );

        foreach ($actions as $action) {
            $data = $presenter->present($action);

            $this->newLine();

            $this->line(
                strtoupper($data['priority'])
            );

            $this->line(
                $action->project->name
            );

            $this->line(
                $data['action']
            );

            $this->line(
                'Reason: '.$data['reason']
            );

            foreach ($data['evidence'] as $evidence) {
                $this->line(
                    'Evidence: '.$evidence['description']
                );
            }

            $timeline =
                $timelinePresenter->present($action);

            foreach ($timeline['timeline'] as $event) {
                $this->line(
                    'Timeline: '.$event['type']
                );
            }
        }

        return self::SUCCESS;
    }
}
