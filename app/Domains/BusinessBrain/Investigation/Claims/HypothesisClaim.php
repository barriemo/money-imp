<?php

namespace App\Domains\BusinessBrain\Investigation\Claims;

class HypothesisClaim
{
    public function __construct(
        public string $key,

        public string $statement,

        public string $status = 'unknown',

        public int $confidence = 0,

        public array $evidence = []
    ) {}
}
