<?php

namespace App\Console\Commands;

use App\Domains\BusinessBrain\Project\Services\ProjectBriefService;
use Illuminate\Console\Command;

class ProjectBriefCommand extends Command
{
    protected $signature = 'project:brief';

    protected $description =
        'Show the current Money Imp Project brief';

    public function handle(
        ProjectBriefService $service
    ): int {
        $brief =
            $service->current();

        $this->line(
            'MONEY IMP'
        );

        $this->line(
            'Project Imp'
        );

        $this->newLine();

        $this->line(
            'Active projects: '.
            $brief->activeProjects
        );

        $this->line(
            'Blocked projects: '.
            $brief->blockedProjects
        );

        $this->line(
            'At risk projects: '.
            $brief->atRiskProjects
        );

        if (
            count($brief->priorityProjects) > 0
        ) {
            $this->newLine();

            $this->line(
                'Priority projects:'
            );

            foreach (
                $brief->priorityProjects as $project
            ) {
                $this->newLine();

                $this->line(
                    $project['name']
                );

                $this->line(
                    'Status: '.
                    strtoupper(
                        $project['health']
                    )
                );

                if (
                    count(
                        $project['reasons']
                    ) > 0
                ) {
                    $this->line(
                        'Reasons:'
                    );

                    foreach (
                        $project['reasons'] as $reason
                    ) {
                        $this->line(
                            '- '.$reason
                        );
                    }
                }

                if (
                    $project['recommended_action']
                ) {
                    $this->line(
                        'Recommended action: '.
                        $project['recommended_action']
                    );
                }
            }
        }

        if (
            count($brief->overdueDeliverables) > 0
        ) {
            $this->newLine();

            $this->line(
                'Delivery risks:'
            );

            foreach (
                $brief->overdueDeliverables as $deliverable
            ) {
                $this->line(
                    '- '.$deliverable['project']
                );

                $this->line(
                    '  Deliverable: '.
                    $deliverable['deliverable']
                );

                if (
                    $deliverable['owner']
                ) {
                    $this->line(
                        '  Owner: '.
                        $deliverable['owner']
                    );
                }

                $this->line(
                    '  Status: OVERDUE'
                );

                $this->line(
                    '  Due: '.
                    $deliverable['due_date']
                );
            }
        }

        if (
            count($brief->updateRequests) > 0
        ) {
            $this->newLine();

            $this->line(
                'Outstanding update requests:'
            );

            foreach (
                $brief->updateRequests as $request
            ) {
                $this->line(
                    '- '.$request['project']
                );

                if (
                    $request['requested_from']
                ) {
                    $this->line(
                        '  Requested from: '.
                        $request['requested_from']
                    );
                }

                $this->line(
                    '  Reason: '.
                    $request['reason']
                );
            }
        }

        return self::SUCCESS;
    }
}
