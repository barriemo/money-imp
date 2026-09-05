<?php

namespace App\Domains\Billing\Decision;

use App\Domains\CommercialTruth\DTO\CanonicalServiceObservedBilling;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BillingDecisionContext
{
    public const TRUTH_BOUNDARY =
        'Canonical observed billing describes supported billing evidence for the exact client service. '
        .'It does not by itself establish what should be billed or create a billing obligation. '
        .'No canonical observed billing does not mean no billing obligation exists.';

    public function __construct(
        public BillingDecisionRequest $request,
        public string $clientId,
        public string $clientName,
        public string $serviceName,
        public string $serviceStatus,
        public ?CanonicalServiceObservedBilling $observedBilling,
        public string $truthBoundary,
        public CarbonImmutable $observedAt,
    ) {
        if (trim($this->clientId) === '') {
            throw new InvalidArgumentException(
                'Billing decision context client id cannot be empty.'
            );
        }

        if (trim($this->clientName) === '') {
            throw new InvalidArgumentException(
                'Billing decision context client name cannot be empty.'
            );
        }

        if (trim($this->serviceName) === '') {
            throw new InvalidArgumentException(
                'Billing decision context service name cannot be empty.'
            );
        }

        if (trim($this->serviceStatus) === '') {
            throw new InvalidArgumentException(
                'Billing decision context service status cannot be empty.'
            );
        }

        if (trim($this->truthBoundary) === '') {
            throw new InvalidArgumentException(
                'Billing decision context truth boundary cannot be empty.'
            );
        }

        if ($this->observedBilling === null) {
            return;
        }

        if (
            $this->observedBilling->clientServiceId
            !== $this->request->clientServiceId
        ) {
            throw new InvalidArgumentException(
                'Billing decision context observed billing must belong to the requested client service.'
            );
        }

        if (
            $this->observedBilling->clientId
            !== $this->clientId
        ) {
            throw new InvalidArgumentException(
                'Billing decision context observed billing must belong to the subject client.'
            );
        }
    }
}
