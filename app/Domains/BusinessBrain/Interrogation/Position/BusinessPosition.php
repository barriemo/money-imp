<?php

namespace App\Domains\BusinessBrain\Interrogation\Position;

class BusinessPosition
{
    public function __construct(
        public int $clientCount,

        public int $invoiceCount,

        public float $grossInvoiced,

        public float $outstanding,

        public int $bankTransactionCount,

        public int $unmatchedBankTransactionCount,

        public int $openCharlieFindingCount,

        public int $clientsWithOpenCharlieFindings
    ) {}
}
