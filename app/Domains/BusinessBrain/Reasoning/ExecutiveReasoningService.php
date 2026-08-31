<?php

namespace App\Domains\BusinessBrain\Reasoning;

use App\Domains\BusinessBrain\Reasoning\Engines\OpportunityEngine;
use Illuminate\Support\Collection;

class ExecutiveReasoningService
{
    public function __construct(
        private OpportunityEngine $opportunities,

    ) {}

    public function opportunities(
        int $limit = 10
    ): Collection {
        return $this->opportunities
            ->current()
            ->sortByDesc(
                'score'
            )
            ->take(
                $limit
            )
            ->values();
    }
}
