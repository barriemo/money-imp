<?php

namespace App\Domains\BusinessBrain\Interrogation\Coverage;

class BusinessTruthCoverage
{
    public function __construct(
        public string $client,

        public int $invoiceCount,

        public int $bankTransactionCount,

        public int $paymentIdentityCount,

        public int $workLogCount,

        public int $serviceCount,

        public int $openCharlieFindingCount,

        public bool $hasInvoices,

        public bool $hasBankTransactions,

        public bool $hasPaymentIdentity,

        public bool $hasWorkLogs,

        public bool $hasServices,

        public bool $hasCharlieFindings,

        public int $confidence
    ) {}
}
