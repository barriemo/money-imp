<?php

namespace App\Domains\BusinessBrain\BusinessState;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BusinessStateProjection
{
    public function __construct(
        public Collection $financialFacts,

        public Collection $commercialFacts,

        public Collection $workFacts,

        public Collection $commercialConditions,

        public Collection $unknowns,

        public Collection $evidenceGaps,

        public CarbonImmutable $asOf
    ) {}
}
