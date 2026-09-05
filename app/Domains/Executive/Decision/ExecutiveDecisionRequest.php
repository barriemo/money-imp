<?php

namespace App\Domains\Executive\Decision;

use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use InvalidArgumentException;

class ExecutiveDecisionRequest
{
    public function __construct(
        public string $key,
        public string $question,
        public ?CfoDecisionRequest $cfoRequest = null,
        public ?CommercialDecisionRequest $commercialRequest = null,
        public ?DeliveryDecisionRequest $deliveryRequest = null,
        public array $parameters = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Executive decision request key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Executive decision request question cannot be empty.'
            );
        }

        foreach (
            $this->parameters as $name => $value
        ) {
            if (
                ! is_string($name)
                || trim($name) === ''
            ) {
                throw new InvalidArgumentException(
                    'Executive decision request parameter names must be non-empty strings.'
                );
            }

            if (
                $value !== null
                && ! is_scalar($value)
            ) {
                throw new InvalidArgumentException(
                    'Executive decision request parameters must contain only scalar or null values.'
                );
            }

            if (
                is_float($value)
                && ! is_finite($value)
            ) {
                throw new InvalidArgumentException(
                    'Executive decision request numeric parameters must be finite.'
                );
            }
        }
    }

    public function hasCfoRequest(): bool
    {
        return $this->cfoRequest !== null;
    }

    public function hasCommercialRequest(): bool
    {
        return $this->commercialRequest !== null;
    }

    public function hasDeliveryRequest(): bool
    {
        return $this->deliveryRequest !== null;
    }
}
