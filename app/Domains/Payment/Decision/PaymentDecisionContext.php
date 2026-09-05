<?php

namespace App\Domains\Payment\Decision;

use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientPaymentEvidenceSearchResult;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class PaymentDecisionContext
{
    public function __construct(
        public PaymentDecisionRequest $request,
        public ClientPaymentEvidenceSearchResult $paymentEvidence,
        public CarbonImmutable $observedAt,
    ) {
        /*
         * The upstream payment-evidence search is an established,
         * client-attributable read model.
         *
         * Its state remains upstream factual/evidence context here.
         * Payment OS policy interpretation belongs to Stage 11.3.
         */
        if (
            $this->paymentEvidence->clientId
            !== $this->request->clientId
        ) {
            throw new InvalidArgumentException(
                'Payment decision context evidence must belong to the requested client.'
            );
        }

        if (trim($this->paymentEvidence->state) === '') {
            throw new InvalidArgumentException(
                'Payment decision context evidence state cannot be empty.'
            );
        }

        if (trim($this->paymentEvidence->truthBoundary) === '') {
            throw new InvalidArgumentException(
                'Payment decision context truth boundary cannot be empty.'
            );
        }
    }
}
