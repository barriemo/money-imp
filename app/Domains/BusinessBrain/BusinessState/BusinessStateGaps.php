<?php

namespace App\Domains\BusinessBrain\BusinessState;

use Illuminate\Support\Collection;

class BusinessStateGaps
{
    public function __construct(
        public Collection $unknowns,

        public Collection $evidenceGaps
    ) {}
}
