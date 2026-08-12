<?php

namespace App\Domains\BusinessBrain\Attention\Brief;

use Illuminate\Support\Collection;

class AttentionBrief
{
    public function __construct(
        public int $totalSignals,

        public int $highestPriority,

        public Collection $signals,
    ) {}
}
