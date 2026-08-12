<?php

namespace App\Domains\ResourceIntelligence;

class Resource
{
    public function __construct(
        public string $name,

        public string $type,

        public array $skills = [],

        public ?float $costRate = null,
    ) {}
}
