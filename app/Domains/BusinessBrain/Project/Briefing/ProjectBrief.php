<?php

namespace App\Domains\BusinessBrain\Project\Briefing;

use Carbon\CarbonImmutable;

class ProjectBrief
{
    public function __construct(
        public int $activeProjects,

        public int $blockedProjects,

        public int $atRiskProjects,

        public array $priorityProjects,

        public array $risks,

        public array $overdueDeliverables,

        public array $updateRequests,

        public CarbonImmutable $asOf
    ) {}
}
