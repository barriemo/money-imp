<?php

namespace App\Domains\Attribution;

class AttributionEvidence
{
    public function normalise(
        array $evidence
    ): array {
        return [
            'type' => $evidence['type']
                ?? 'unknown',

            'summary' => $evidence['summary']
                ?? null,

            'confidence' => max(
                0,
                min(
                    100,
                    (int) (
                        $evidence['confidence']
                        ?? 50
                    )
                )
            ),

            'reference' => $evidence['reference']
                ?? null,

            'metadata' => $evidence['metadata']
                ?? [],
        ];
    }

    public function key(
        array $evidence
    ): string {
        return hash(
            'sha256',
            implode('|', [
                $evidence['type']
                    ?? '',

                $evidence['reference']
                    ?? '',

                strtolower(
                    trim(
                        (string) (
                            $evidence['summary']
                            ?? ''
                        )
                    )
                ),
            ])
        );
    }
}
