<?php

namespace App\Domains\BusinessBrain\Investigation\Timeline;

use Illuminate\Support\Collection;

class InvestigationEpisode
{
    public function __construct(
        public ?string $correlationId,

        public ?string $trigger,

        public Collection $events,

        public Collection $claimChanges,

        public Collection $hypothesisChanges,

        public ?string $outcome = null,

        public bool $legacy = false
    ) {}
}
