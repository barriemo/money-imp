<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BusinessStateExplanationEvidenceSet
{
    public function __construct(
        public BusinessStateChange $observation,
        public Collection $evidence,
        public ?string $interpretation,
        public string $impact,
    ) {
        if (trim($this->impact) === '') {
            throw new InvalidArgumentException(
                'Explanation evidence set requires an impact statement.'
            );
        }

        if (
            ! $this->evidence->every(
                fn (mixed $item): bool => $item instanceof BusinessStateExplanationEvidence
            )
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence set must contain only explanation evidence.'
            );
        }

        if (
            $this->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::CONTEXT
                )
                ->isEmpty()
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence set requires observation context.'
            );
        }

        if (
            $this->interpretation !== null
            && trim($this->interpretation) === ''
        ) {
            throw new InvalidArgumentException(
                'Explanation evidence set interpretation cannot be empty.'
            );
        }

        $supports =
            $this->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                );

        $contradicts =
            $this->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::CONTRADICTS
                );

        if (
            $this->interpretation === null
            && (
                $supports->isNotEmpty()
                || $contradicts->isNotEmpty()
            )
        ) {
            throw new InvalidArgumentException(
                'Positioned evidence requires an interpretation.'
            );
        }

        if (
            $this->interpretation !== null
            && $supports->isEmpty()
        ) {
            throw new InvalidArgumentException(
                'Interpretations require supporting evidence.'
            );
        }
    }
}
