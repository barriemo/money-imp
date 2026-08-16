<?php

namespace App\Domains\BusinessBrain\BankTruth;

use Illuminate\Support\Collection;

class CanonicalBankTransaction
{
    public function __construct(
        public string $id,

        public string $date,

        public float $amount,

        public ?string $clientId,

        public ?string $description,

        public ?string $bankAccountId,

        public Collection $evidence,

        public string $resolution,

        public int $confidence
    ) {}
}
