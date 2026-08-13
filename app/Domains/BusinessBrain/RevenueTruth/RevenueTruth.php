<?php

namespace App\Domains\BusinessBrain\RevenueTruth;

class RevenueTruth
{
    public function __construct(
        public string $clientId,

        public string $client,

        public int $invoiceCount,

        public float $grossInvoiced,

        public float $paidAccordingToAccounting,

        public float $outstanding,

        public int $workLogCount,

        public float $workCommercialValue,

        public float $unrecoveredWorkValue,

        public float $bankVerifiedPaymentValue,

        public int $paymentEvidenceConfidence,

        public int $commercialConfidence
    ) {}
}
