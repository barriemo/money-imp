<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Position;

class PaymentTruthPosition
{
    public function __construct(
        public float $canonicalReceived,

        public float $allocatedReceived,

        public float $suggestedReceived,

        public float $unmatchedReceived,

        public int $paymentCount,

        public int $allocatedPaymentCount,

        public int $suggestedPaymentCount,

        public int $unmatchedPaymentCount,

        public int $duplicateEvidenceGroups,

        public float $duplicateEvidenceValue,

        public int $confidence
    ) {}
}
