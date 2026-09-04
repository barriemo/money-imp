<?php

namespace App\Domains\Commercial\Decision;

use InvalidArgumentException;

class CommercialDecisionRequest
{
    public function __construct(
        public string $key,
        public string $question,
        public ?string $clientId = null,
        public ?string $candidateFingerprint = null,
        public ?string $evidenceFingerprint = null,
        public array $parameters = [],
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Commercial decision request key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Commercial decision request question cannot be empty.'
            );
        }

        if (
            $this->clientId !== null
            && trim($this->clientId) === ''
        ) {
            throw new InvalidArgumentException(
                'Commercial decision request client id cannot be empty.'
            );
        }

        if (
            $this->candidateFingerprint !== null
            && trim($this->candidateFingerprint) === ''
        ) {
            throw new InvalidArgumentException(
                'Commercial decision request candidate fingerprint cannot be empty.'
            );
        }

        if (
            $this->evidenceFingerprint !== null
            && trim($this->evidenceFingerprint) === ''
        ) {
            throw new InvalidArgumentException(
                'Commercial decision request evidence fingerprint cannot be empty.'
            );
        }

        if (
            $this->candidateFingerprint !== null
            && $this->clientId === null
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate subjects require a client id.'
            );
        }

        if (
            $this->evidenceFingerprint !== null
            && $this->candidateFingerprint === null
        ) {
            throw new InvalidArgumentException(
                'Commercial decision evidence fingerprints require a candidate fingerprint.'
            );
        }

        if (
            $this->candidateFingerprint !== null
            && $this->evidenceFingerprint === null
        ) {
            throw new InvalidArgumentException(
                'Commercial decision candidate subjects require an evidence fingerprint.'
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
                    'Commercial decision request parameter names must be non-empty strings.'
                );
            }

            if (
                $value !== null
                && ! is_scalar($value)
            ) {
                throw new InvalidArgumentException(
                    'Commercial decision request parameters must contain only scalar or null values.'
                );
            }

            if (
                is_float($value)
                && ! is_finite($value)
            ) {
                throw new InvalidArgumentException(
                    'Commercial decision request numeric parameters must be finite.'
                );
            }
        }
    }

    public function hasClientSubject(): bool
    {
        return $this->clientId !== null;
    }

    public function hasCandidateSubject(): bool
    {
        return $this->candidateFingerprint !== null
            && $this->evidenceFingerprint !== null;
    }
}
