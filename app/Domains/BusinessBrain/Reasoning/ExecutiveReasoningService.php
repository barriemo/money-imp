<?php

namespace App\Domains\BusinessBrain\Reasoning;

use App\Domains\BusinessBrain\Reasoning\Engines\CashManagementReasoningEngine;
use App\Domains\BusinessBrain\Reasoning\Engines\OpportunityEngine;
use Illuminate\Support\Collection;

class ExecutiveReasoningService
{
    public function __construct(
        private OpportunityEngine $opportunities,

        private CashManagementReasoningEngine $cashManagement
    ) {}

    public function opportunities(
        int $limit = 10
    ): Collection {
        return collect([
            $this->opportunities
                ->current(),

            $this->cashManagement
                ->current(),
        ])
            ->flatten(1)
            ->sortByDesc(
                'score'
            )
            ->take(
                $limit
            )
            ->values();
    }
}
