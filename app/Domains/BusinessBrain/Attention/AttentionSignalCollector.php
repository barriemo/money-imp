<?php

namespace App\Domains\BusinessBrain\Attention;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Attention\Contracts\AttentionSignalProvider;
use Illuminate\Support\Collection;

class AttentionSignalCollector
{
    public function __construct(
        private iterable $providers
    ) {}

    public function collect(
        AttentionContext $context
    ): Collection {
        return collect($this->providers)
            ->flatMap(
                fn (AttentionSignalProvider $provider) => $provider->provide($context)
            )
            ->values();
    }
}
