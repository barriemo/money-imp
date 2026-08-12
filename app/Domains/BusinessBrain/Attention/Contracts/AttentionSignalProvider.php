<?php

namespace App\Domains\BusinessBrain\Attention\Contracts;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use Illuminate\Support\Collection;

interface AttentionSignalProvider
{
    public function provide(
        AttentionContext $context
    ): Collection;
}
