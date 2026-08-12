<?php

namespace App\Domains\Reasoning\Strategies;

use App\Domains\Reasoning\Answer;
use App\Domains\Reasoning\Contracts\ReasoningStrategy;
use App\Domains\Reasoning\Question;
use App\Domains\Reasoning\ReasoningEvidence;
use App\Domains\TruthGraph\TruthGraphNode;

class RevenueRecoveryReasoner implements ReasoningStrategy
{
    public function supports(
        Question $question
    ): bool {
        return $question->type
            === 'revenue_recovery';
    }

    public function answer(
        array $graph,
        Question $question
    ): Answer {
        $recommendations =
            collect(
                $graph['nodes']
                ?? []
            )
                ->filter(
                    fn (TruthGraphNode $node) => $node->type
                        === 'revenue_recommendation'
                )
                ->values();

        $monthly =
            round(
                (float)
                $recommendations
                    ->sum(
                        fn (TruthGraphNode $node) => (float) (
                            $node->attributes[
                                'estimated_monthly_value'
                            ]
                            ?? 0
                        )
                    ),
                2
            );

        $annual =
            round(
                (float)
                $recommendations
                    ->sum(
                        fn (TruthGraphNode $node) => (float) (
                            $node->attributes[
                                'estimated_annual_value'
                            ]
                            ?? 0
                        )
                    ),
                2
            );

        $evidence =
            $recommendations
                ->map(
                    fn (TruthGraphNode $node) => new ReasoningEvidence(
                        nodeKey: $node->key(),

                        summary: $node->label,

                        confidence: $node->confidence,

                        metadata: [
                            'estimated_monthly_value' => $node->attributes[
                                    'estimated_monthly_value'
                                ]
                                ?? 0,

                            'estimated_annual_value' => $node->attributes[
                                    'estimated_annual_value'
                                ]
                                ?? 0,
                        ]
                    )
                );

        $confidence =
            $recommendations->isEmpty()
                ? 100
                : (int) round(
                    $recommendations
                        ->avg(
                            fn (TruthGraphNode $node) => $node->confidence
                        )
                );

        $summary =
            $recommendations->isEmpty()
                ? 'No open revenue recovery recommendations are currently supported by the graph.'
                : 'The graph contains '
                    .$recommendations->count()
                    .' open revenue recovery '
                    .str('recommendation')
                        ->plural(
                            $recommendations->count()
                        )
                    .' worth approximately £'
                    .number_format(
                        $monthly,
                        2
                    )
                    .' per month.';

        return new Answer(
            questionType: $question->type,

            summary: $summary,

            evidence: $evidence,

            confidence: $confidence,

            data: [
                'recommendation_count' => $recommendations->count(),

                'monthly_value' => $monthly,

                'annual_value' => $annual,
            ]
        );
    }
}
