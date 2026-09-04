<?php

namespace App\Domains\Cfo\Decision;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CfoDecision
{
    public const RECOMMENDED =
        'recommended';

    public const CONDITIONAL =
        'conditional';

    public const DEFERRED =
        'deferred';

    public const STATUSES = [
        self::RECOMMENDED,
        self::CONDITIONAL,
        self::DEFERRED,
    ];

    public function __construct(
        public string $key,
        public string $question,
        public string $status,
        public ?string $recommendation,
        public string $rationale,
        public Collection $evidence,
        public Collection $constraints,
        public int $confidence,
        public Collection $missingTruth,
        public CarbonImmutable $asOf,
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException(
                'CFO decision key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'CFO decision question cannot be empty.'
            );
        }

        if (
            ! in_array(
                $this->status,
                self::STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid CFO decision status.'
            );
        }

        if (
            $this->recommendation !== null
            && trim($this->recommendation) === ''
        ) {
            throw new InvalidArgumentException(
                'CFO decision recommendation cannot be an empty string.'
            );
        }

        if (trim($this->rationale) === '') {
            throw new InvalidArgumentException(
                'CFO decision rationale cannot be empty.'
            );
        }

        if (
            ! $this->evidence->every(
                fn (mixed $item): bool => $item instanceof CfoDecisionEvidence
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision evidence must contain only CFO decision evidence.'
            );
        }

        if (
            ! $this->constraints->every(
                fn (mixed $item): bool => $item instanceof CfoDecisionConstraint
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision constraints must contain only CFO decision constraints.'
            );
        }

        if (
            ! $this->missingTruth->every(
                fn (mixed $item): bool => is_string($item)
                    && trim($item) !== ''
            )
        ) {
            throw new InvalidArgumentException(
                'CFO decision missing truth must contain only non-empty strings.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'CFO decision confidence must be between 0 and 100.'
            );
        }

        $supports =
            $this->evidence
                ->where(
                    'position',
                    CfoDecisionEvidence::SUPPORTS
                );

        $contradictions =
            $this->evidence
                ->where(
                    'position',
                    CfoDecisionEvidence::CONTRADICTS
                );

        $blocking =
            $this->constraints
                ->where(
                    'type',
                    CfoDecisionConstraint::BLOCKING
                );

        if ($this->status === self::DEFERRED) {
            if ($this->recommendation !== null) {
                throw new InvalidArgumentException(
                    'Deferred CFO decisions cannot contain a recommendation.'
                );
            }

            if ($this->confidence !== 0) {
                throw new InvalidArgumentException(
                    'Deferred CFO decisions must have recommendation confidence 0.'
                );
            }

            if (
                $blocking->isEmpty()
                && $this->missingTruth->isEmpty()
            ) {
                throw new InvalidArgumentException(
                    'Deferred CFO decisions require a blocking constraint or explicit missing truth.'
                );
            }

            return;
        }

        if ($this->recommendation === null) {
            throw new InvalidArgumentException(
                'Recommended or conditional CFO decisions require a recommendation.'
            );
        }

        if ($this->confidence <= 0) {
            throw new InvalidArgumentException(
                'Recommended or conditional CFO decisions require positive recommendation confidence.'
            );
        }

        if ($supports->isEmpty()) {
            throw new InvalidArgumentException(
                'Recommended or conditional CFO decisions require supporting evidence.'
            );
        }

        $weakestSupportConfidence =
            (int) $supports
                ->min(
                    'confidence'
                );

        if (
            $this->confidence
            > $weakestSupportConfidence
        ) {
            throw new InvalidArgumentException(
                'CFO decision confidence cannot exceed the weakest supporting evidence.'
            );
        }

        if ($this->status === self::RECOMMENDED) {
            if ($contradictions->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended CFO decisions cannot contain contradictory evidence.'
                );
            }

            if ($this->constraints->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended CFO decisions cannot contain unresolved constraints.'
                );
            }

            if ($this->missingTruth->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended CFO decisions cannot contain missing truth.'
                );
            }

            return;
        }

        if ($blocking->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Conditional CFO decisions cannot contain blocking constraints.'
            );
        }

        if (
            $contradictions->isEmpty()
            && $this->constraints->isEmpty()
            && $this->missingTruth->isEmpty()
        ) {
            throw new InvalidArgumentException(
                'Conditional CFO decisions require explicit uncertainty.'
            );
        }
    }
}
