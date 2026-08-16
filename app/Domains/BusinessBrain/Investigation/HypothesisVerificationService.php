<?php

namespace App\Domains\BusinessBrain\Investigation;

class HypothesisVerificationService
{
    /**
     * @param  array<int, EvidenceCollector>  $collectors
     */
    public function verify(
        Hypothesis $hypothesis,
        array $collectors
    ): InvestigationResult {
        $evidence = [];

        foreach ($collectors as $collector) {
            foreach (
                $collector->collect(
                    $hypothesis
                ) as $item
            ) {
                $evidence[] =
                    $item;
            }
        }

        $supports =
            collect(
                $evidence
            )
                ->where(
                    'position',
                    'supports'
                );

        $contradicts =
            collect(
                $evidence
            )
                ->where(
                    'position',
                    'contradicts'
                );

        $missing =
            collect(
                $evidence
            )
                ->where(
                    'position',
                    'missing'
                );

        $status =
            $this->status(
                supports: $supports->count(),
                contradicts: $contradicts->count(),
                missing: $missing->count()
            );

        return new InvestigationResult(
            hypothesis: $hypothesis,

            status: $status,

            confidence: $this->confidence(
                $supports->all(),
                $contradicts->all(),
                $missing->all()
            ),

            evidence: $evidence,

            missingEvidence: $missing
                ->pluck(
                    'description'
                )
                ->values()
                ->all(),

            recommendation: $this->recommendation(
                $status
            )
        );
    }

    private function status(
        int $supports,
        int $contradicts,
        int $missing
    ): string {
        if (
            $supports > 0
            && $contradicts === 0
            && $missing === 0
        ) {
            return 'verified';
        }

        if (
            $contradicts > 0
            && $supports === 0
        ) {
            return 'contradicted';
        }

        if (
            $supports > 0
            && $missing > 0
        ) {
            return 'plausible';
        }

        return 'unknown';
    }

    private function confidence(
        array $supports,
        array $contradicts,
        array $missing
    ): int {
        $supportScore =
            collect(
                $supports
            )
                ->avg(
                    'confidence'
                ) ?? 0;

        $contradictionScore =
            collect(
                $contradicts
            )
                ->avg(
                    'confidence'
                ) ?? 0;

        $missingPenalty =
            min(
                40,
                count(
                    $missing
                ) * 10
            );

        $score =
            $supportScore
            - $contradictionScore
            - $missingPenalty;

        return max(
            0,
            min(
                100,
                (int) round(
                    abs(
                        $score
                    )
                )
            )
        );
    }

    private function recommendation(
        string $status
    ): string {
        return match ($status) {
            'verified' => 'The available evidence supports this hypothesis.',

            'contradicted' => 'The available evidence contradicts this hypothesis.',

            'plausible' => 'The hypothesis is plausible, but important evidence is still missing.',

            default => 'There is not enough evidence to reach a reliable conclusion.',
        };
    }
}
