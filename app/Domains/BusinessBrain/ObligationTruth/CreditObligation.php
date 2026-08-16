<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

class CreditObligation
{
    public function __construct(
        public string $facilityId,

        public string $facility,

        public float $amountDue,

        public string $dueAt,

        public string $status,

        public ?float $matchedPayment,

        public ?string $matchedAt,

        public int $confidence
    ) {}
}
