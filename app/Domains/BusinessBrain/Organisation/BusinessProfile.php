<?php

namespace App\Domains\BusinessBrain\Organisation;

class BusinessProfile
{
    public function __construct(
        public readonly string $name,

        public readonly string $industry,

        public readonly array $priorities = []
    ) {}
}