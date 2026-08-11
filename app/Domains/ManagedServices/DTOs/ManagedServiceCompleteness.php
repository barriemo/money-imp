<?php

namespace App\Domains\ManagedServices\DTOs;

use App\Models\ManagedService;
use App\Models\ManagedServiceTemplate;

readonly class ManagedServiceCompleteness
{
    public function __construct(
        public ManagedService $service,
        public ManagedServiceTemplate $template,
        public float $score,
        public array $present,
        public array $missing,
        public array $recommendedMissing,
    ) {}

    public function complete(): bool
    {
        return $this->missing === [];
    }
}
