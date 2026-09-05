<?php

namespace App\Domains\Payment\Decision;

use InvalidArgumentException;

class PaymentDecisionRequest
{
    public function __construct(
        public string $key,
        public string $question,
        public string $clientId,
        public array $parameters = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Payment decision request key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Payment decision request question cannot be empty.'
            );
        }

        if (trim($this->clientId) === '') {
            throw new InvalidArgumentException(
                'Payment decision request client id cannot be empty.'
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
                    'Payment decision request parameter names must be non-empty strings.'
                );
            }

            if (
                $value !== null
                && ! is_scalar($value)
            ) {
                throw new InvalidArgumentException(
                    'Payment decision request parameters must contain only scalar or null values.'
                );
            }

            if (
                is_float($value)
                && ! is_finite($value)
            ) {
                throw new InvalidArgumentException(
                    'Payment decision request numeric parameters must be finite.'
                );
            }
        }
    }
}
