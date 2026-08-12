<?php

namespace App\Domains\VATIntelligence;

class VATExposure
{
    public function __construct(
        public float $liability,

        public int $priority,

        public string $reason
    ) {}
}
