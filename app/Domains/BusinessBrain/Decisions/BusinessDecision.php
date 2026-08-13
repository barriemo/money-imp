<?php

namespace App\Domains\BusinessBrain\Decisions;

class BusinessDecision
{
    public function __construct(
        public string $type,

        public string $clientId,

        public string $client,

        public string $action,

        public string $reason,

        public int $priority,

        public ?float $value,

        public int $confidence
    ) {}
}
