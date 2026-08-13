<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

class CommercialGap
{
    public function __construct(
        public string $type,

        public string $clientId,

        public string $client,

        public string $title,

        public string $description,

        public ?float $value,

        public int $priority,

        public int $confidence
    ) {}
}
