<?php

namespace App\Domains\ResourceIntelligence;

class ResourceSkill
{
    public function __construct(
        public string $name,

        public int $confidence = 100,
    ) {}
}
