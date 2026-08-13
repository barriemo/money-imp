<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

use Illuminate\Support\Collection;

class RevenueTruthSummary
{
    public function __construct(
        public int $clientCount,

        public float $grossInvoiced,

        public float $paidAccordingToAccounting,

        public float $outstanding,

        public float $unrecoveredWorkValue,

        public float $bankVerifiedPaymentValue,

        public int $clientsWithOutstandingRevenue,

        public int $clientsWithWeakPaymentEvidence,

        public int $clientsWithoutWorkEvidence,

        public int $averageCommercialConfidence,

        public Collection $gaps
    ) {}
}
