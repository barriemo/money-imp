<?php

namespace App\Domains\BusinessBrain\BusinessState;

use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BusinessState
{
    public function __construct(
        public FinancialPosition $financial,

        public RevenueTruthSummary $revenue,

        public Collection $clients,

        public BusinessStateGaps $gaps,

        public CarbonImmutable $asOf
    ) {}
}
