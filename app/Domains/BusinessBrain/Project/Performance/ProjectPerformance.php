<?php

namespace App\Domains\BusinessBrain\Project\Performance;

class ProjectPerformance
{
    public function __construct(
        public int $openUpdateRequests,

        public int $resolvedUpdateRequests,

        public ?float $averageResponseDays,

        public array $slowRespondingProjects
    ) {}
}
