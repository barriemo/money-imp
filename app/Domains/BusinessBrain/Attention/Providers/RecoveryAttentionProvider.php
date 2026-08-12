<?php

namespace App\Domains\BusinessBrain\Attention\Providers;

use App\Domains\BusinessBrain\Attention\Builders\RecoveryAttentionSignalBuilder;
use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Attention\Contracts\AttentionSignalProvider;
use Illuminate\Support\Collection;

class RecoveryAttentionProvider implements AttentionSignalProvider
{
    public function __construct(
        private RecoveryAttentionSignalBuilder $builder
    ) {}

    public function provide(
        AttentionContext $context
    ): Collection {
        if (
            ! $context->recovery
        ) {
            return collect();
        }

        $signal =
            $this->builder->build(
                $context->recovery
            );

        return collect([
            $signal,
        ])
            ->filter()
            ->values();
    }
}
