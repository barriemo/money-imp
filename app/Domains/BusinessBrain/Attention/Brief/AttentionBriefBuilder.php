<?php

namespace App\Domains\BusinessBrain\Attention\Brief;

use App\Domains\BusinessBrain\Attention\AttentionRanker;
use Illuminate\Support\Collection;

class AttentionBriefBuilder
{
    public function __construct(
        private AttentionRanker $ranker
    ) {}

    public function build(
        Collection $signals
    ): AttentionBrief {
        $ranked =
            $this->ranker->rank(
                $signals
            );

        return new AttentionBrief(
            totalSignals: $ranked->count(),

            highestPriority: $ranked
                ->first()
                ?->priority ?? 0,

            signals: $ranked
        );
    }
}
