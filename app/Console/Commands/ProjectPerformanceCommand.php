<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Services\ProjectPerformanceService;
use Illuminate\Console\Command;

class ProjectPerformanceCommand extends Command
{
    protected $signature = 'project:performance';

    protected $description =
        'Show Project Imp delivery performance';

    public function handle(
        ProjectPerformanceService $service
    ): int {
        $performance =
            $service->current();

        $this->line(
            'MONEY IMP'
        );

        $this->line(
            'Project Imp Performance'
        );

        $this->newLine();

        $this->line(
            'Open update requests: '.
            $performance->openUpdateRequests
        );

        $this->line(
            'Resolved update requests: '.
            $performance->resolvedUpdateRequests
        );

        $this->line(
            'Average response time: '.
            (
                $performance->averageResponseDays !== null
                    ? $performance->averageResponseDays.' days'
                    : 'No data'
            )
        );

        if (
            count($performance->slowRespondingProjects) > 0
        ) {
            $this->newLine();

            $this->line(
                'Slow responding projects:'
            );

            foreach (
                $performance->slowRespondingProjects as $project
            ) {
                $this->line(
                    '- '.$project['project']
                );

                $this->line(
                    '  Requests: '.
                    $project['requests']
                );

                $this->line(
                    '  Average response: '.
                    $project['average_days'].
                    ' days'
                );
            }
        }

        return self::SUCCESS;
    }
}
