<?php

namespace App\Domains\Billing\Decision;

use InvalidArgumentException;

class BillingDecisionRequest
{
    public function __construct(
        public string $key,
        public string $question,
        public string $clientServiceId,
        public array $parameters = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Billing decision request key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Billing decision request question cannot be empty.'
            );
        }

        if (trim($this->clientServiceId) === '') {
            throw new InvalidArgumentException(
                'Billing decision request client service id cannot be empty.'
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
                    'Billing decision request parameter names must be non-empty strings.'
                );
            }

            if (
                $value !== null
                && ! is_scalar($value)
            ) {
                throw new InvalidArgumentException(
                    'Billing decision request parameters must contain only scalar or null values.'
                );
            }

            if (
                is_float($value)
                && ! is_finite($value)
            ) {
                throw new InvalidArgumentException(
                    'Billing decision request numeric parameters must be finite.'
                );
            }
        }
    }
}
