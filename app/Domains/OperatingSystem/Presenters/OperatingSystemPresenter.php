<?php

namespace App\Domains\OperatingSystem\Presenters;

use App\Domains\BusinessBrain\Project\Services\ProjectBriefService;
use App\Domains\BusinessBrain\Project\Services\ProjectPerformanceService;
use App\Domains\OperatingSystem\DTOs\SpecialistDefinition;
use App\Domains\OperatingSystem\Services\OperatingSystemService;

class OperatingSystemPresenter
{
    public function __construct(
        private OperatingSystemService $operatingSystem,

        private ProjectBriefService $projectBrief,

        private ProjectPerformanceService $projectPerformance
    ) {}

    public function present(): string
    {
        $lines = [
            'MONEY IMP',
            'Business Operating System',
            '',
            'Mission:',
            'Build an evidence-led executive team that continuously understands the business and helps humans make better decisions.',
            '',
            'Specialists:',
        ];

        foreach (
            $this->operatingSystem->specialists() as $specialist
        ) {
            $lines[] =
                $this->specialistLine(
                    $specialist
                );
        }

        $lines[] = '';
        $lines[] = sprintf(
            'Capabilities registered: %d',
            $this->operatingSystem
                ->capabilities()
                ->count()
        );

        $brief =
            $this->projectBrief
                ->current();

        $performance =
            $this->projectPerformance
                ->current();

        $lines[] = '';
        $lines[] = 'Project Imp';

        $lines[] =
            'Active projects: '.
            $brief->activeProjects;

        $lines[] =
            'Blocked projects: '.
            $brief->blockedProjects;

        $lines[] =
            'At risk projects: '.
            $brief->atRiskProjects;

        $lines[] = '';

        $lines[] = 'Delivery performance:';

        $lines[] =
            'Open update requests: '.
            $performance->openUpdateRequests;

        $lines[] =
            'Resolved update requests: '.
            $performance->resolvedUpdateRequests;

        $lines[] =
            'Average response time: '.
            (
                $performance->averageResponseDays !== null
                    ? $performance->averageResponseDays.' days'
                    : 'No data'
            );

        $lines[] = '';
        $lines[] = 'Next recommended work:';
        $lines[] =
            $this->operatingSystem
                ->nextRecommendedWork();

        return implode(
            PHP_EOL,
            $lines
        );
    }

    private function specialistLine(
        SpecialistDefinition $specialist
    ): string {
        return sprintf(
            '- %s [%s / %s]',
            $specialist->name,
            strtoupper(
                $specialist->status
            ),
            strtoupper(
                $specialist->phase
            )
        );
    }
}
