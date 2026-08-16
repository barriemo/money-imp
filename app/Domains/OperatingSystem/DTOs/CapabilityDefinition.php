<?php

namespace App\Domains\OperatingSystem\DTOs;

final readonly class CapabilityDefinition
{
    public function __construct(
        public string $key,
        public string $name,
        public string $owner,
        public string $phase,
        public string $status,
    ) {}
}
