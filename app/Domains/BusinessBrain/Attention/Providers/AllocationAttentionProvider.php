<?php

namespace App\Domains\BusinessBrain\Attention\Providers;

use App\Domains\BusinessBrain\Attention\Builders\AllocationAttentionSignalBuilder;
use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Attention\Contracts\AttentionSignalProvider;
use Illuminate\Support\Collection;

class AllocationAttentionProvider implements AttentionSignalProvider
{
    public function __construct(
        private AllocationAttentionSignalBuilder $builder
    ) {}

    public function provide(
        AttentionContext $context
    ): Collection {
        if (
            ! $context->allocation
        ) {
            return collect();
        }

        $signal =
            $this->builder->build(
                $context->client,
                $context->allocation
            );

        return collect([
            $signal,
        ])
            ->filter()
            ->values();
    }
}
