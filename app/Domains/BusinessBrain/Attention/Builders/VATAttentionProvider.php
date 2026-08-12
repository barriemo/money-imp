<?php

namespace App\Domains\BusinessBrain\Attention\Builders;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Attention\Contracts\AttentionSignalProvider;
use Illuminate\Support\Collection;

class VATAttentionProvider implements AttentionSignalProvider
{
    public function __construct(
        private VATAttentionSignalBuilder $builder
    ) {}

    public function provide(
        AttentionContext $context
    ): Collection {
        if (
            ! $context->vat
        ) {
            return collect();
        }

        $signal =
            $this->builder->build(
                'Business',
                $context->vat
            );

        return collect([
            $signal,
        ])
            ->filter()
            ->values();
    }
}
