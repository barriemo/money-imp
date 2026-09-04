<?php

namespace App\Domains\BusinessBrain\BusinessState\Explanation;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class BusinessStateExplanationPolicy
{
    public function __construct(
        private BusinessStateExplanationMissingTruthCatalog $missingTruth
    ) {}

    public function assess(
        BusinessStateExplanationEvidenceSet $set
    ): BusinessStateExplanation {
        $missingTruth =
            $this->missingTruth
                ->forEvidenceSet(
                    $set
                );

        if ($set->interpretation === null) {
            if ($missingTruth->isEmpty()) {
                throw new InvalidArgumentException(
                    'Unestablished explanation policy requires explicit missing truth.'
                );
            }

            return new BusinessStateExplanation(
                observation: $set->observation,

                status: BusinessStateExplanation::UNESTABLISHED,

                evidence: $set->evidence,

                interpretation: null,

                impact: $set->impact,

                confidence: 0,

                missingTruth: $missingTruth
            );
        }

        $supports =
            $set->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::SUPPORTS
                )
                ->values();

        $contradicts =
            $set->evidence
                ->where(
                    'position',
                    BusinessStateExplanationEvidence::CONTRADICTS
                )
                ->values();

        $confidence =
            $this->supportConfidence(
                $supports
            );

        $status =
            (
                $contradicts->isNotEmpty()
                || $missingTruth->isNotEmpty()
            )
                ? BusinessStateExplanation::PARTIAL
                : BusinessStateExplanation::ESTABLISHED;

        return new BusinessStateExplanation(
            observation: $set->observation,

            status: $status,

            evidence: $set->evidence,

            interpretation: $set->interpretation,

            impact: $set->impact,

            confidence: $confidence,

            missingTruth: $missingTruth
        );
    }

    private function supportConfidence(
        Collection $supports
    ): int {
        if ($supports->isEmpty()) {
            throw new InvalidArgumentException(
                'Explanation interpretation requires supporting evidence.'
            );
        }

        /*
         * Do not blend unrelated evidence into an arbitrary confidence value.
         *
         * Interpretation confidence cannot exceed the weakest evidence
         * item explicitly positioned as SUPPORTS.
         *
         * Contradiction affects status, not through invented arithmetic.
         */
        $confidence =
            (int) $supports
                ->min(
                    'confidence'
                );

        if ($confidence <= 0) {
            throw new InvalidArgumentException(
                'Supported explanation requires positive supporting confidence.'
            );
        }

        return $confidence;
    }
}
