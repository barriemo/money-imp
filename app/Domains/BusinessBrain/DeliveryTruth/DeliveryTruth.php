<?php

namespace App\Domains\BusinessBrain\DeliveryTruth;

class DeliveryTruth
{
    public function __construct(
        public string $clientId,

        public string $client,

        public int $workLogCount,

        public int $invoicedWorkLogCount,

        public int $uninvoicedWorkLogCount,

        public float $commercialValue,

        public float $invoicedCommercialValue,

        public float $uninvoicedCommercialValue,

        public int $invoiceLinkageConfidence
    ) {}
}
