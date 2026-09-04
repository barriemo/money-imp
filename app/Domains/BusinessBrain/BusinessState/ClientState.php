<?php

namespace App\Domains\BusinessBrain\BusinessState;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverage;

class ClientState
{
    public function __construct(
        public string $clientId,

        public string $client,

        public DeliveryTruth $delivery,

        public BusinessTruthCoverage $coverage
    ) {}
}
