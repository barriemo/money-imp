<?php

namespace App\Domains\BusinessBrain\Timeline;

use App\Models\Client;
use Illuminate\Support\Collection;

final readonly class ClientTimeline
{
    public function __construct(
        public Client $client,

        public Collection $events
    ) {}
}
