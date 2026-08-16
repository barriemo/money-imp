<?php

namespace App\Domains\BusinessBrain\Project\Communication;

use Carbon\CarbonImmutable;

final readonly class ProjectCommunicationMemory
{
    public function __construct(
        public string $type,

        public string $direction,

        public string $summary,

        public ?string $commitment,

        public ?string $requestedBy,

        public ?CarbonImmutable $occurredAt,
    ) {}
}
