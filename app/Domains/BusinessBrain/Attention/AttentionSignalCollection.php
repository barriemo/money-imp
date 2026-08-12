<?php

namespace App\Domains\BusinessBrain\Attention;

use Illuminate\Support\Collection;

class AttentionSignalCollection
{
    public function __construct(
        public Collection $signals
    ) {}

    public function highestPriority(): ?AttentionSignal
    {
        return $this->signals
            ->sortByDesc(
                fn (AttentionSignal $signal) => $signal->priority
            )
            ->first();
    }
}
