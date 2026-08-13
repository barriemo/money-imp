<?php

namespace App\Domains\BusinessBrain\Interrogation;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\MorningBrief\Services\MorningBriefService;
use InvalidArgumentException;

class BusinessInterrogator
{
    public function __construct(
        private MorningBriefService $morningBrief
    ) {}

    public function ask(
        BusinessQuestion $question,
        AttentionContext $context
    ): BusinessAnswer {
        return match ($question->normalised()) {
            'where are we?',
            'where are we' => $this->whereAreWe(
                $question,
                $context
            ),

            default => throw new InvalidArgumentException(
                'Unsupported business question: '.$question->question
            ),
        };
    }

    private function whereAreWe(
        BusinessQuestion $question,
        AttentionContext $context
    ): BusinessAnswer {
        $brief =
            $this->morningBrief->build(
                $context
            );

        $recovery =
            (float) (
                $brief->signals
                    ->firstWhere(
                        'type',
                        'recovery'
                    )
                    ?->value
                ?? 0
            );

        $allocation =
            (float) (
                $brief->signals
                    ->firstWhere(
                        'type',
                        'allocation_variance'
                    )
                    ?->value
                ?? 0
            );

        $vat =
            (float) (
                $brief->signals
                    ->firstWhere(
                        'type',
                        'vat_exposure'
                    )
                    ?->value
                ?? 0
            );

        $totalExposure =
            $recovery
            + $allocation
            + $vat;

        $highestPriority =
            $brief->signals
                ->sortByDesc(
                    'priority'
                )
                ->first();

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                '%s currently has £%s of identified commercial exposure across %d attention signals.',
                $context->client,
                number_format(
                    $totalExposure,
                    2
                ),
                $brief->signals->count()
            ),

            facts: [
                'client' => $context->client,

                'signal_count' => $brief->signals->count(),

                'total_exposure' => $totalExposure,

                'recovery_value' => $recovery,

                'allocation_exposure' => $allocation,

                'vat_exposure' => $vat,

                'highest_priority_type' => $highestPriority?->type,

                'highest_priority_value' => $highestPriority?->value,
            ],

            evidence: $brief->signals
                ->map(
                    fn ($signal) => [
                        'type' => $signal->type,

                        'value' => $signal->value,

                        'priority' => $signal->priority,

                        'reason' => $signal->reason,
                    ]
                )
                ->values()
                ->all(),

            confidence: $brief->signals->isEmpty()
                ? 0
                : 90,

            asOf: now()
        );
    }
}
