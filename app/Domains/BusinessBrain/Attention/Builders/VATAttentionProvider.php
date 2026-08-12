<?php

namespace App\Domains\BusinessBrain\Attention\Builders;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\VATIntelligence\VATExposure;

class VATAttentionProvider
{
    public function __construct(
        private VATAttentionSignalBuilder $builder
    ) {}

    public function provide(
        string $entity,
        VATExposure $exposure
    ): ?AttentionSignal {
        return $this->builder->build(
            $entity,
            $exposure
        );
    }
}
