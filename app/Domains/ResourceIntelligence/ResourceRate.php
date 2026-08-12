<?php

namespace App\Domains\ResourceIntelligence;

class ResourceRate
{
    public function __construct(
        public float $amount,

        public string $basis = 'hourly',
    ) {}
}
