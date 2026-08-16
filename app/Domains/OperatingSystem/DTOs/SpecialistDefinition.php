<?php

namespace App\Domains\OperatingSystem\DTOs;

final readonly class SpecialistDefinition
{
    public function __construct(
        public string $key,
        public string $name,
        public string $purpose,
        public string $phase,
        public string $status,
        public array $dependencies = [],
    ) {}
}
