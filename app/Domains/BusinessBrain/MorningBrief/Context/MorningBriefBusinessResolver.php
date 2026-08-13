<?php

namespace App\Domains\BusinessBrain\MorningBrief\Context;

use App\Models\Client;
use Illuminate\Support\Collection;

class MorningBriefBusinessResolver
{
    public function resolve(): Collection
    {
        return Client::query()
            ->get();
    }
}
