<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Services\ProjectUpdateRequestService;
use Illuminate\Console\Command;

class ProjectUpdateCheckCommand extends Command
{
    protected $signature = 'project:check-updates';

    protected $description =
        'Check projects for missing progress updates';

    public function handle(
        ProjectUpdateRequestService $service
    ): int {
        $requests =
            $service->generate();

        $this->line(
            'MONEY IMP'
        );

        $this->line(
            'Project Imp Update Check'
        );

        $this->newLine();

        $this->line(
            'New update requests created: '.
            count($requests)
        );

        foreach (
            $requests as $request
        ) {
            $this->newLine();

            $this->line(
                $request->project->name
            );

            if (
                $request->requested_from
            ) {
                $this->line(
                    'Requested from: '.
                    $request->requested_from
                );
            }

            $this->line(
                'Reason: '.
                $request->reason
            );
        }

        return self::SUCCESS;
    }
}
