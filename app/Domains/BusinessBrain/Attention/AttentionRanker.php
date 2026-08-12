<?php

namespace App\Domains\BusinessBrain\Attention;

use Illuminate\Support\Collection;

class AttentionRanker
{
    public function rank(
        Collection $signals
    ): Collection {
        return $signals
            ->sortByDesc(
                fn (AttentionSignal $signal) => $signal->priority
            )
            ->values();
    }
}
