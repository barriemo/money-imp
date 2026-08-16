<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Analysis;

class UnmatchedPaymentAnalysis
{
    public function __construct(
        public int $paymentCount,

        public float $paymentValue,

        public int $uniqueExactMatchCount,

        public float $uniqueExactMatchValue,

        public int $ambiguousExactMatchCount,

        public float $ambiguousExactMatchValue,

        public int $noExactMatchCount,

        public float $noExactMatchValue
    ) {}
}
