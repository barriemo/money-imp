<?php

namespace App\Domains\BusinessBrain\BusinessState\Change;

use InvalidArgumentException;

class BusinessStateMetric
{
    public function __construct(
        public string $domain,
        public string $metric,
        public string $scope,
        public ?string $clientId,
        public ?string $client,
        public string $source,
        public bool $known,
        public int|float|null $value,
    ) {
        if (! in_array($this->scope, ['business', 'client'], true)) {
            throw new InvalidArgumentException(
                'Business state metric scope must be business or client.'
            );
        }

        if (
            trim($this->domain) === ''
            || trim($this->metric) === ''
            || trim($this->source) === ''
        ) {
            throw new InvalidArgumentException(
                'Business state metric domain, metric and source must be present.'
            );
        }

        if (
            $this->scope === 'business'
            && $this->clientId !== null
        ) {
            throw new InvalidArgumentException(
                'Business-scoped metrics cannot have a client id.'
            );
        }

        if (
            $this->scope === 'client'
            && $this->clientId === null
        ) {
            throw new InvalidArgumentException(
                'Client-scoped metrics require a client id.'
            );
        }

        /*
         * Known and unknown are truth states.
         *
         * Zero is a valid known value.
         *
         * Therefore:
         *
         * unknown + value is invalid
         * known + null is invalid
         */
        if (
            $this->known
            && $this->value === null
        ) {
            throw new InvalidArgumentException(
                'Known business state metrics require a value.'
            );
        }

        if (
            ! $this->known
            && $this->value !== null
        ) {
            throw new InvalidArgumentException(
                'Unknown business state metrics cannot carry a value.'
            );
        }
    }

    public function key(): string
    {
        return implode(
            '::',
            [
                $this->domain,
                $this->scope,
                $this->clientId ?? 'business',
                $this->metric,
            ]
        );
    }
}
