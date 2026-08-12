<?php

namespace App\Domains\BusinessBrain\Attention\Contracts;

use Illuminate\Support\Collection;

interface AttentionSignalProvider
{
    public function provide(): Collection;
}
