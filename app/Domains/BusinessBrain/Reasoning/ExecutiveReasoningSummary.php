<?php

namespace App\Domains\BusinessBrain\Reasoning;

use Illuminate\Support\Collection;

class ExecutiveReasoningSummary
{
    public function __construct(
        public int $opportunityCount,

        public float $knownFinancialImpact,

        public float $quickWinFinancialImpact,

        public int $quickWinCount,

        public int $financialOpportunityCount,

        public int $financialControlCount,

        public int $deliveryControlCount,

        public int $operationalOpportunityCount,

        public ?ExecutiveReasoning $highestOpportunity,

        public Collection $topOpportunities
    ) {}
}
