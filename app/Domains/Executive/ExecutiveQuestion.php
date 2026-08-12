<?php

namespace App\Domains\Executive;

class ExecutiveQuestion
{
    public function __construct(
        public string $type,
        public array $context = []
    ) {}

    public static function canKeepLightsOn(): self
    {
        return new self(
            type: 'can_keep_lights_on'
        );
    }
}
