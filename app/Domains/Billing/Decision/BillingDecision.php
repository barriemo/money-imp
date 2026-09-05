<?php

namespace App\Domains\Billing\Decision;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class BillingDecision
{
    public const STATUS_RECOMMENDED = 'recommended';

    public const STATUS_CONDITIONAL = 'conditional';

    public const STATUS_DEFERRED = 'deferred';

    public function __construct(
        public readonly string $key,
        public readonly string $question,
        public readonly string $status,
        public readonly ?string $recommendation,
        public readonly string $rationale,
        public readonly Collection $evidence,
        public readonly Collection $constraints,
        public readonly int $confidence,
        public readonly Collection $missingTruth,
        public readonly CarbonImmutable $asOf,
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'Billing decision key must not be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Billing decision question must not be empty.'
            );
        }

        if (! in_array(
            $this->status,
            [
                self::STATUS_RECOMMENDED,
                self::STATUS_CONDITIONAL,
                self::STATUS_DEFERRED,
            ],
            true,
        )) {
            throw new InvalidArgumentException(
                'Billing decision status is invalid.'
            );
        }

        if (
            $this->recommendation !== null
            && trim($this->recommendation) === ''
        ) {
            throw new InvalidArgumentException(
                'Billing decision recommendation must be null or non-empty.'
            );
        }

        if (trim($this->rationale) === '') {
            throw new InvalidArgumentException(
                'Billing decision rationale must not be empty.'
            );
        }

        foreach ($this->evidence as $item) {
            if (! $item instanceof BillingDecisionEvidence) {
                throw new InvalidArgumentException(
                    'Billing decision evidence must contain only BillingDecisionEvidence.'
                );
            }
        }

        foreach ($this->constraints as $item) {
            if (! $item instanceof BillingDecisionConstraint) {
                throw new InvalidArgumentException(
                    'Billing decision constraints must contain only BillingDecisionConstraint.'
                );
            }
        }

        foreach ($this->missingTruth as $item) {
            if (
                ! is_string($item)
                || trim($item) === ''
            ) {
                throw new InvalidArgumentException(
                    'Billing decision missing truth must contain only non-empty strings.'
                );
            }
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'Billing decision confidence must be between 0 and 100.'
            );
        }

        $supportingEvidence = $this->evidence
            ->filter(
                fn (BillingDecisionEvidence $item): bool => $item->position
                    === BillingDecisionEvidence::POSITION_SUPPORTS
            )
            ->values();

        $contradictingEvidence = $this->evidence
            ->filter(
                fn (BillingDecisionEvidence $item): bool => $item->position
                    === BillingDecisionEvidence::POSITION_CONTRADICTS
            )
            ->values();

        $blockingConstraints = $this->constraints
            ->filter(
                fn (BillingDecisionConstraint $item): bool => $item->type
                    === BillingDecisionConstraint::TYPE_BLOCKING
            )
            ->values();

        if (
            in_array(
                $this->status,
                [
                    self::STATUS_RECOMMENDED,
                    self::STATUS_CONDITIONAL,
                ],
                true,
            )
        ) {
            if ($this->recommendation === null) {
                throw new InvalidArgumentException(
                    'Recommended or conditional Billing decisions require a recommendation.'
                );
            }

            if ($this->confidence <= 0) {
                throw new InvalidArgumentException(
                    'Recommended or conditional Billing decisions require positive confidence.'
                );
            }

            if ($supportingEvidence->isEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended or conditional Billing decisions require supporting evidence.'
                );
            }

            $weakestSupport = (int) $supportingEvidence
                ->min('confidence');

            if ($this->confidence > $weakestSupport) {
                throw new InvalidArgumentException(
                    'Billing decision confidence cannot exceed the weakest supporting evidence.'
                );
            }
        }

        if ($this->status === self::STATUS_RECOMMENDED) {
            if ($contradictingEvidence->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended Billing decisions cannot contain contradictory evidence.'
                );
            }

            if ($this->constraints->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended Billing decisions cannot contain constraints.'
                );
            }

            if ($this->missingTruth->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended Billing decisions cannot contain missing truth.'
                );
            }
        }

        if ($this->status === self::STATUS_CONDITIONAL) {
            if ($blockingConstraints->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Conditional Billing decisions cannot contain blocking constraints.'
                );
            }

            if (
                $this->constraints->isEmpty()
                && $contradictingEvidence->isEmpty()
                && $this->missingTruth->isEmpty()
            ) {
                throw new InvalidArgumentException(
                    'Conditional Billing decisions require explicit uncertainty.'
                );
            }
        }

        if ($this->status === self::STATUS_DEFERRED) {
            if ($this->recommendation !== null) {
                throw new InvalidArgumentException(
                    'Deferred Billing decisions cannot contain a recommendation.'
                );
            }

            if ($this->confidence !== 0) {
                throw new InvalidArgumentException(
                    'Deferred Billing decisions require zero confidence.'
                );
            }

            if (
                $blockingConstraints->isEmpty()
                && $this->missingTruth->isEmpty()
            ) {
                throw new InvalidArgumentException(
                    'Deferred Billing decisions require a blocker or missing truth.'
                );
            }
        }
    }
}
