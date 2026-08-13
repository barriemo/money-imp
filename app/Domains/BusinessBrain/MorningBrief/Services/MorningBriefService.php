<?php

namespace App\Domains\BusinessBrain\MorningBrief\Services;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\MorningBrief\MorningBrief;
use App\Domains\BusinessBrain\MorningBrief\MorningBriefBuilder;

class MorningBriefService
{
    public function __construct(
        private MorningBriefBuilder $builder
    ) {}

    public function build(
        AttentionContext $context
    ): MorningBrief {
        return $this->builder->build(
            $context
        );
    }
}
