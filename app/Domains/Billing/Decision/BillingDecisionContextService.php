<?php

namespace App\Domains\Billing\Decision;

use App\Domains\CommercialTruth\Services\CanonicalServiceObservedBillingService;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BillingDecisionContextService
{
    public function __construct(
        private CanonicalServiceObservedBillingService $observedBilling,
    ) {}

    public function forDecision(
        BillingDecisionRequest $request,
        ?CarbonImmutable $observedAt = null
    ): BillingDecisionContext {
        $observedAt ??=
            CarbonImmutable::now();

        $service =
            ClientService::query()
                ->with('client')
                ->find(
                    $request->clientServiceId
                );

        if ($service === null) {
            throw new InvalidArgumentException(
                'Billing decision subject client service does not exist.'
            );
        }

        if ($service->client === null) {
            throw new InvalidArgumentException(
                'Billing decision subject client service has no current client.'
            );
        }

        $canonicalObservedBilling =
            $this->observedBilling
                ->forService(
                    $service,
                    $observedAt
                );

        return new BillingDecisionContext(
            request: $request,

            clientId: (string) $service
                ->client
                ->getKey(),

            clientName: (string) $service
                ->client
                ->name,

            serviceName: (string) $service
                ->name,

            serviceStatus: (string) $service
                ->status,

            observedBilling: $canonicalObservedBilling,

            truthBoundary: BillingDecisionContext::TRUTH_BOUNDARY,

            observedAt: $observedAt
        );
    }
}
