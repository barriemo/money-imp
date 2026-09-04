<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BusinessStateExplanation
{
    public const ESTABLISHED =
        'established';

    public const PARTIAL =
        'partial';

    public const UNESTABLISHED =
        'unestablished';

    public const STATUSES = [
        self::ESTABLISHED,
        self::PARTIAL,
        self::UNESTABLISHED,
    ];

    public function __construct(
        public BusinessStateChange $observation,
        public string $status,
        public Collection $evidence,
        public ?string $interpretation,
        public string $impact,
        public int $confidence,
        public Collection $missingTruth,
    ) {
        if (
            ! in_array(
                $this->status,
                self::STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported business state explanation status.'
            );
        }

        if (trim($this->impact) === '') {
            throw new InvalidArgumentException(
                'Business state explanation requires an impact statement.'
            );
        }

        if (
            $this->confidence < 0
            || $this->confidence > 100
        ) {
            throw new InvalidArgumentException(
                'Business state explanation confidence must be between 0 and 100.'
            );
        }

        if (
            ! $this->evidence->every(
                fn (mixed $item): bool => $item instanceof BusinessStateExplanationEvidence
            )
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence must contain only explanation evidence items.'
            );
        }

        if (
            ! $this->missingTruth->every(
                fn (mixed $item): bool => is_string($item)
                    && trim($item) !== ''
            )
        ) {
            throw new InvalidArgumentException(
                'Missing truth must contain only non-empty statements.'
            );
        }

        if (
            $this->interpretation !== null
            && trim($this->interpretation) === ''
        ) {
            throw new InvalidArgumentException(
                'Explanation interpretation cannot be empty.'
            );
        }

        $supports =
            $this->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                )
                ->count();

        $contradicts =
            $this->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::CONTRADICTS
                )
                ->count();

        if ($this->status === self::UNESTABLISHED) {
            $this->guardUnestablished(
                supports: $supports,

                contradicts: $contradicts
            );

            return;
        }

        if ($this->status === self::ESTABLISHED) {
            $this->guardEstablished(
                supports: $supports,

                contradicts: $contradicts
            );

            return;
        }

        $this->guardPartial(
            supports: $supports,

            contradicts: $contradicts
        );
    }

    private function guardUnestablished(
        int $supports,
        int $contradicts,
    ): void {
        if ($this->interpretation !== null) {
            throw new InvalidArgumentException(
                'Unestablished explanations cannot carry an interpretation.'
            );
        }

        if ($this->confidence !== 0) {
            throw new InvalidArgumentException(
                'Unestablished explanations must have zero interpretation confidence.'
            );
        }

        if (
            $supports > 0
            || $contradicts > 0
        ) {
            throw new InvalidArgumentException(
                'Unestablished explanations cannot position evidence against an absent interpretation.'
            );
        }

        if ($this->missingTruth->isEmpty()) {
            throw new InvalidArgumentException(
                'Unestablished explanations must state missing truth.'
            );
        }
    }

    private function guardEstablished(
        int $supports,
        int $contradicts,
    ): void {
        if ($this->interpretation === null) {
            throw new InvalidArgumentException(
                'Established explanations require an interpretation.'
            );
        }

        if ($supports === 0) {
            throw new InvalidArgumentException(
                'Established explanations require supporting evidence.'
            );
        }

        if ($contradicts > 0) {
            throw new InvalidArgumentException(
                'Established explanations cannot contain contradictory evidence.'
            );
        }

        if ($this->missingTruth->isNotEmpty()) {
            throw new InvalidArgumentException(
                'Established explanations cannot retain unresolved missing truth.'
            );
        }

        if ($this->confidence === 0) {
            throw new InvalidArgumentException(
                'Established explanations require positive interpretation confidence.'
            );
        }
    }

    private function guardPartial(
        int $supports,
        int $contradicts,
    ): void {
        if ($this->interpretation === null) {
            throw new InvalidArgumentException(
                'Partial explanations require an interpretation.'
            );
        }

        if ($supports === 0) {
            throw new InvalidArgumentException(
                'Partial explanations require supporting evidence.'
            );
        }

        if ($this->confidence === 0) {
            throw new InvalidArgumentException(
                'Partial explanations require positive interpretation confidence.'
            );
        }

        if (
            $contradicts === 0
            && $this->missingTruth->isEmpty()
        ) {
            throw new InvalidArgumentException(
                'Partial explanations require explicit uncertainty.'
            );
        }
    }
}
