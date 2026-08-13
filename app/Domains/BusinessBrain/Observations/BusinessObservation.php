<?php

namespace App\Domains\BusinessBrain\Observations;

class BusinessObservation
{
    public function __construct(
        public string $type,

        public string $title,

        public string $message,

        public int $priority,

        public ?string $clientId,

        public ?string $client,

        public ?float $value,

        public int $confidence
    ) {}
}
