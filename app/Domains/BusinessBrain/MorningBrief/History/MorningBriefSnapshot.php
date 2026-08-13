<?php

namespace App\Domains\BusinessBrain\MorningBrief\History;

use Illuminate\Support\Collection;

class MorningBriefSnapshot
{
    public function __construct(
        public string $client,

        public int $signalCount,

        public Collection $signals,

        public \DateTimeInterface $generatedAt
    ) {}
}
