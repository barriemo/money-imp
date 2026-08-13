<?php

namespace App\Domains\BusinessBrain\MorningBrief;

use Illuminate\Support\Collection;

class MorningBrief
{
    public function __construct(
        public Collection $signals
    ) {}
}
