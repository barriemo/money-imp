<?php

namespace App\Domains\BusinessBrain\CreditTruth;

class CreditFacilityTruth
{
    public function __construct(
        public string $id,

        public string $provider,

        public string $name,

        public string $type,

        public ?float $creditLimit,

        public ?float $reportedBalance,

        public ?float $availableCredit,

        public ?float $minimumPayment,

        public ?string $paymentDueAt,

        public bool $verified,

        public int $confidence,

        public string $status,

        public ?string $balanceAt
    ) {}
}
