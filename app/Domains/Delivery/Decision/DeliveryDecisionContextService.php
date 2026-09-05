<?php

namespace App\Domains\Delivery\Decision;

use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruthService;
use App\Models\Client;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class DeliveryDecisionContextService
{
    public function __construct(
        private DeliveryTruthService $deliveryTruth,
    ) {}

    public function forDecision(
        DeliveryDecisionRequest $request,
        ?CarbonImmutable $observedAt = null
    ): DeliveryDecisionContext {
        $observedAt ??=
            CarbonImmutable::now();

        $client =
            Client::query()
                ->find(
                    $request->clientId
                );

        if ($client === null) {
            throw new InvalidArgumentException(
                'Delivery decision subject client does not exist.'
            );
        }

        /*
         * Delivery OS v1 composes only the established,
         * client-attributable DeliveryTruth contract here.
         *
         * Project / deliverable machinery is deliberately excluded:
         * archaeology has not established it as canonical
         * client-attributable delivery truth.
         *
         * This service assembles context only. It does not interpret
         * absence as health, convert linkage confidence into decision
         * confidence, or produce recommendation guidance.
         */
        $truth =
            $this->deliveryTruth
                ->forClient(
                    $client
                );

        return new DeliveryDecisionContext(
            request: $request,
            deliveryTruth: $truth,
            hasRecordedDeliveryEvidence: $truth->workLogCount > 0,
            observedAt: $observedAt
        );
    }
}
