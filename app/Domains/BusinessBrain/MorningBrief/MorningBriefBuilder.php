<?php

namespace App\Domains\BusinessBrain\MorningBrief;

use App\Domains\BusinessBrain\Attention\AttentionRanker;
use App\Domains\BusinessBrain\Attention\AttentionSignalCollector;
use App\Domains\BusinessBrain\Attention\Context\AttentionContext;

class MorningBriefBuilder
{
    public function __construct(
        private AttentionSignalCollector $collector,

        private AttentionRanker $ranker
    ) {}

    public function build(
        AttentionContext $context
    ): MorningBrief {
        $signals =
            $this->collector->collect(
                $context
            );

        return new MorningBrief(
            signals: $this->ranker->rank(
                $signals
            )
        );
    }
}
