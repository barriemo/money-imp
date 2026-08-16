<?php

namespace App\Console\Commands;

use App\Models\ProjectAction;
use Illuminate\Console\Command;

class ProjectActionsCommand extends Command
{
    protected $signature = 'project:actions';

    protected $description = 'Show Project Imp action queue';

    public function handle(): int
    {
        $actions =
            ProjectAction::query()
                ->with('project')
                ->where(
                    'status',
                    'open'
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

        $this->line(
            'MONEY IMP'
        );

        $this->line(
            'Project Imp Action Queue'
        );

        $this->newLine();

        $this->line(
            'Open actions: '.$actions->count()
        );

        foreach ($actions as $action) {
            $this->newLine();

            $this->line(
                strtoupper(
                    $action->priority
                )
            );

            $this->line(
                $action->project->name
            );

            $this->line(
                $action->action
            );

            $this->line(
                'Reason: '.$action->reason
            );
        }

        return self::SUCCESS;
    }
}
