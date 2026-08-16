<?php

namespace App\Domains\BusinessBrain\Project\Recommendations;

class ProjectRecommendation
{
    public function __construct(
        public string $project,

        public string $priority,

        public string $reason,

        public string $action
    ) {}
}
