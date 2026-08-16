<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Presenters\ProjectActionPresenter;
use App\Domains\BusinessBrain\Project\Presenters\ProjectActionTimelinePresenter;
use App\Models\ProjectAction;
use Illuminate\Console\Command;

class ProjectHistoryCommand extends Command
{
    protected $signature = 'project:history';

    protected $description = 'Show Project Imp action history';

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
                ])
                ->latest()
                ->get();

        $this->line('MONEY IMP');

        $this->line('Project Action History');

        $this->newLine();

        $this->line(
            'Actions recorded: '.$actions->count()
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
                'Status: '.$data['status']
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
