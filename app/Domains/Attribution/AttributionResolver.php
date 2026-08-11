<?php

namespace App\Domains\Attribution;

use App\Models\AttributionCandidate;

class AttributionResolver
{
    public function __construct(
        private AttributionEvidence $evidence,
        private AttributionConfidence $confidence
    ) {}

    public function propose(
        string $subjectType,
        string $subjectId,
        string $relationshipType,
        ?string $targetType = null,
        ?string $targetId = null,
        string $source = 'attribution_engine',
        ?string $reason = null,
        array $evidence = [],
        array $metadata = []
    ): AttributionCandidate {
        $fingerprint =
            $this->fingerprint(
                subjectType: $subjectType,
                subjectId: $subjectId,
                relationshipType: $relationshipType,
                targetType: $targetType,
                targetId: $targetId
            );

        $candidate =
            AttributionCandidate::query()
                ->where(
                    'fingerprint',
                    $fingerprint
                )
                ->first();

        $existingEvidence =
            collect(
                $candidate?->evidence
                ?? []
            );

        $newEvidence =
            collect(
                $evidence
            )
                ->map(
                    fn (array $item) => $this->evidence
                        ->normalise(
                            $item
                        )
                );

        $combinedEvidence =
            $existingEvidence
                ->merge(
                    $newEvidence
                )
                ->unique(
                    fn (array $item) => $this->evidence
                        ->key(
                            $item
                        )
                )
                ->values();

        $confidence =
            $this->confidence
                ->calculate(
                    $combinedEvidence
                );

        return AttributionCandidate::updateOrCreate(
            [
                'fingerprint' => $fingerprint,
            ],
            [
                'subject_type' => $subjectType,

                'subject_id' => $subjectId,

                'relationship_type' => $relationshipType,

                'target_type' => $targetType,

                'target_id' => $targetId,

                'confidence' => $confidence,

                'status' => $candidate?->status
                    ?? 'candidate',

                'source' => $source,

                'reason' => $reason
                    ?? $candidate?->reason,

                'evidence' => $combinedEvidence
                    ->all(),

                'metadata' => array_merge(
                    $candidate?->metadata
                    ?? [],
                    $metadata
                ),
            ]
        );
    }

    private function fingerprint(
        string $subjectType,
        string $subjectId,
        string $relationshipType,
        ?string $targetType,
        ?string $targetId
    ): string {
        return hash(
            'sha256',
            implode('|', [
                strtolower(
                    trim(
                        $subjectType
                    )
                ),

                $subjectId,

                strtolower(
                    trim(
                        $relationshipType
                    )
                ),

                strtolower(
                    trim(
                        $targetType
                        ?? ''
                    )
                ),

                $targetId
                    ?? 'unknown',
            ])
        );
    }
}
