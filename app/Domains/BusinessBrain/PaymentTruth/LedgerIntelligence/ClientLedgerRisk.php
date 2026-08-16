<?php

namespace App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence;

class ClientLedgerRisk
{
    public function __construct(
        public string $clientId,

        public ?string $clientName,

        public string $classification,

        public float $difference,

        public float $cashReceived,

        public float $invoiceValue,

        public int $priority,

        public int $confidence,

        public array $reasons,

        public array $actions
    ) {}
}
