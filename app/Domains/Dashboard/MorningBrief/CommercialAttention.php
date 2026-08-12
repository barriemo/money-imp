<?php

namespace App\Domains\Dashboard\MorningBrief;

class CommercialAttention
{
    public function __construct(
        public int $clientCount,

        public float $recoverableValue,

        public int $openWorkLogs,

        public array $highPriorityClients = [],
    ) {}
}
