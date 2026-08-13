<?php

namespace App\Domains\BusinessBrain\MorningBrief\History;

class MorningBriefChange
{
    public function __construct(
        public string $type,

        public string $signalType,

        public float $previousValue,

        public float $currentValue,

        public float $difference
    ) {}
}
