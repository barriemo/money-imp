<?php

namespace App\Domains\BusinessBrain\Interrogation\Coverage;

class BusinessCoverageSummary
{
    public function __construct(
        public int $clientCount,

        public int $clientsWithoutInvoices,

        public int $clientsWithoutBankTransactions,

        public int $clientsWithoutPaymentIdentities,

        public int $clientsWithoutWorkLogs,

        public int $clientsWithoutServices,

        public int $clientsWithoutCharlieFindings,

        public int $averageCoverageConfidence
    ) {}
}
