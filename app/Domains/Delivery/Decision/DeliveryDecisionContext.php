<?php

namespace App\Domains\Delivery\Decision;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class DeliveryDecisionContext
{
    public function __construct(
        public DeliveryDecisionRequest $request,
        public DeliveryTruth $deliveryTruth,
        public bool $hasRecordedDeliveryEvidence,
        public CarbonImmutable $observedAt,
    ) {
        /*
         * DeliveryTruthService currently exposes a current cumulative
         * WorkLog-backed read. observedAt is therefore the time this
         * decision context was assembled; it is not a historical
         * delivery-truth cutoff invented by Delivery OS.
         *
         * In particular, numeric zeroes from an empty WorkLog dataset
         * remain numeric outputs from absent evidence. They are not
         * proof that delivery, commercial value or uninvoiced value
         * is established as zero.
         */
        if (
            $this->deliveryTruth->clientId
            !== $this->request->clientId
        ) {
            throw new InvalidArgumentException(
                'Delivery decision context truth must belong to the requested client.'
            );
        }

        $expectedEvidencePresence =
            $this->deliveryTruth->workLogCount > 0;

        if (
            $this->hasRecordedDeliveryEvidence
            !== $expectedEvidencePresence
        ) {
            throw new InvalidArgumentException(
                'Delivery decision context evidence presence must match recorded WorkLog truth.'
            );
        }
    }
}
