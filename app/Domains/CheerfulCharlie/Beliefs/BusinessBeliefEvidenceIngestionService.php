<?php

namespace App\Domains\CheerfulCharlie\Beliefs;

use App\Models\BusinessBelief;
use Illuminate\Database\Eloquent\Model;

class BusinessBeliefEvidenceIngestionService
{
    public function __construct(
        private BusinessBeliefService $beliefs
    ) {}

    public function ingest(
        Model $subject,
        string $beliefType,
        string $key,
        string $value,
        Model $evidence,
        int $weight = 80,
        int $confidence = 100,
        ?string $summary = null
    ): BusinessBelief {
        $belief = BusinessBelief::query()
            ->where(
                'subject_type',
                $subject->getMorphClass()
            )
            ->where(
                'subject_id',
                $subject->getKey()
            )
            ->where(
                'belief_type',
                $beliefType
            )
            ->where(
                'key',
                $key
            )
            ->where(
                'status',
                'active'
            )
            ->first();

        if (! $belief) {
            $belief = $this->beliefs
                ->remember(
                    subject: $subject,
                    beliefType: $beliefType,
                    key: $key,
                    value: $value,
                    source: 'evidence'
                );

            $this->beliefs->addEvidence(
                belief: $belief,
                evidence: $evidence,
                relationship: 'supports',
                weight: $weight,
                confidence: $confidence,
                summary: $summary
            );

            return $belief->fresh();
        }

        $relationship =
            $this->sameValue(
                $belief->value,
                $value
            )
                ? 'supports'
                : 'contradicts';

        $this->beliefs->addEvidence(
            belief: $belief,
            evidence: $evidence,
            relationship: $relationship,
            weight: $weight,
            confidence: $confidence,
            summary: $summary,
            metadata: [
                'observed_value' => $value,
            ]
        );

        return $belief->fresh();
    }

    private function sameValue(
        ?string $current,
        string $incoming
    ): bool {
        return $this->normalise(
            $current ?? ''
        )
            ===
            $this->normalise(
                $incoming
            );
    }

    private function normalise(
        string $value
    ): string {
        return strtolower(
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $value
                ) ?? $value
            )
        );
    }
}
