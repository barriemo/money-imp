<?php

namespace App\Domains\Payment\Decision;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentDecision
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
                'Payment decision key cannot be empty.'
            );
        }

        if (trim($this->question) === '') {
            throw new InvalidArgumentException(
                'Payment decision question cannot be empty.'
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
                'Invalid payment decision status.'
            );
        }

        if (
            $this->recommendation !== null
            && trim($this->recommendation) === ''
        ) {
            throw new InvalidArgumentException(
                'Payment decision recommendation cannot be an empty string.'
            );
        }

        if (trim($this->rationale) === '') {
            throw new InvalidArgumentException(
                'Payment decision rationale cannot be empty.'
            );
        }

        if (
            ! $this->evidence->every(
                fn (mixed $item): bool => $item instanceof PaymentDecisionEvidence
            )
        ) {
            throw new InvalidArgumentException(
                'Payment decision evidence must contain only payment decision evidence.'
            );
        }

        if (
            ! $this->constraints->every(
                fn (mixed $item): bool => $item instanceof PaymentDecisionConstraint
            )
        ) {
            throw new InvalidArgumentException(
                'Payment decision constraints must contain only payment decision constraints.'
            );
        }

        if (
            ! $this->missingTruth->every(
                fn (mixed $item): bool => is_string($item)
                    && trim($item) !== ''
            )
        ) {
            throw new InvalidArgumentException(
                'Payment decision missing truth must contain only non-empty strings.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'Payment decision confidence must be between 0 and 100.'
            );
        }

        $supports =
            $this->evidence
                ->where(
                    'position',
                    PaymentDecisionEvidence::SUPPORTS
                );

        $contradictions =
            $this->evidence
                ->where(
                    'position',
                    PaymentDecisionEvidence::CONTRADICTS
                );

        $blocking =
            $this->constraints
                ->where(
                    'type',
                    PaymentDecisionConstraint::BLOCKING
                );

        if ($this->status === self::DEFERRED) {
            if ($this->recommendation !== null) {
                throw new InvalidArgumentException(
                    'Deferred payment decisions cannot contain a recommendation.'
                );
            }

            if ($this->confidence !== 0) {
                throw new InvalidArgumentException(
                    'Deferred payment decisions must have recommendation confidence 0.'
                );
            }

            if (
                $blocking->isEmpty()
                && $this->missingTruth->isEmpty()
            ) {
                throw new InvalidArgumentException(
                    'Deferred payment decisions require a blocking constraint or explicit missing truth.'
                );
            }

            return;
        }

        if ($this->recommendation === null) {
            throw new InvalidArgumentException(
                'Recommended or conditional payment decisions require a recommendation.'
            );
        }

        if ($this->confidence <= 0) {
            throw new InvalidArgumentException(
                'Recommended or conditional payment decisions require positive recommendation confidence.'
            );
        }

        if ($supports->isEmpty()) {
            throw new InvalidArgumentException(
                'Recommended or conditional payment decisions require supporting evidence.'
            );
        }

        /*
         * Recommendation confidence is bounded by the weakest
         * evidence actually supporting the guidance.
         *
         * It is not a weighted score, average, client-risk score,
         * collection score or payment-workflow priority.
         */
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
                'Payment decision confidence cannot exceed the weakest supporting evidence.'
            );
        }

        if ($this->status === self::RECOMMENDED) {
            if ($contradictions->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended payment decisions cannot contain contradictory evidence.'
                );
            }

            if ($this->constraints->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended payment decisions cannot contain unresolved constraints.'
                );
            }

            if ($this->missingTruth->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'Recommended payment decisions cannot contain missing truth.'
                );
            }

            return;
        }

        if ($blocking->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Conditional payment decisions cannot contain blocking constraints.'
            );
        }

        if (
            $contradictions->isEmpty()
            && $this->constraints->isEmpty()
            && $this->missingTruth->isEmpty()
        ) {
            throw new InvalidArgumentException(
                'Conditional payment decisions require explicit uncertainty.'
            );
        }
    }
}
