<?php

namespace App\Domains\BusinessBrain\Investigation\Claims;

use App\Domains\BusinessBrain\Investigation\EvidenceItem;

class HypothesisClaimAssessmentService
{
    /**
     * @param  array<int, EvidenceItem>  $evidence
     */
    public function assess(
        HypothesisClaimSet $claims,
        array $evidence
    ): HypothesisClaimSet {
        foreach ($claims->claims as $claim) {
            $relevant =
                collect(
                    $evidence
                )
                    ->filter(
                        fn (EvidenceItem $item) => $this->relevant(
                            $claim,
                            $item
                        )
                    )
                    ->values();

            $claim->evidence =
                $relevant
                    ->map(
                        fn (EvidenceItem $item) => [
                            'source' => $item->source,
                            'description' => $item->description,
                            'position' => $item->position,
                            'confidence' => $item->confidence,
                        ]
                    )
                    ->all();

            $supports =
                $relevant
                    ->where(
                        'position',
                        'supports'
                    );

            $contradicts =
                $relevant
                    ->where(
                        'position',
                        'contradicts'
                    );

            $missing =
                $relevant
                    ->where(
                        'position',
                        'missing'
                    );

            [$status, $confidence] =
                $this->result(
                    $supports->all(),
                    $contradicts->all(),
                    $missing->all()
                );

            $claim->status =
                $status;

            $claim->confidence =
                $confidence;
        }

        return $claims;
    }

    private function relevant(
        HypothesisClaim $claim,
        EvidenceItem $item
    ): bool {
        return match ($claim->key) {
            'payment_occurred' => in_array(
                $item->source,
                [
                    'accounting',
                    'client_ledger',
                ],
                true
            ),

            'payment_received' => in_array(
                $item->source,
                [
                    'accounting',
                    'bank',
                    'bank_coverage',
                ],
                true
            ),

            'payment_destination_hsbc' => $item->source === 'bank_source',

            default => false,
        };
    }

    private function result(
        array $supports,
        array $contradicts,
        array $missing
    ): array {
        if (
            $contradicts !== []
            && $supports === []
        ) {
            return [
                'contradicted',
                $this->averageConfidence(
                    $contradicts
                ),
            ];
        }

        if (
            $supports !== []
            && $missing === []
            && $contradicts === []
        ) {
            return [
                'supported',
                $this->averageConfidence(
                    $supports
                ),
            ];
        }

        if (
            $supports !== []
            && $missing !== []
        ) {
            return [
                'plausible',
                max(
                    0,
                    $this->averageConfidence(
                        $supports
                    )
                    - min(
                        40,
                        count(
                            $missing
                        ) * 15
                    )
                ),
            ];
        }

        if (
            $missing !== []
        ) {
            return [
                'unverified',
                0,
            ];
        }

        return [
            'unknown',
            0,
        ];
    }

    private function averageConfidence(
        array $items
    ): int {
        if ($items === []) {
            return 0;
        }

        return (int) round(
            collect(
                $items
            )
                ->avg(
                    'confidence'
                )
        );
    }
}
