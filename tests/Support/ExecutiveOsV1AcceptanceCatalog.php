<?php

namespace Tests\Support;

use App\Domains\Executive\Decision\ExecutiveDecision;
use App\Domains\Executive\Decision\ExecutiveDecisionContext;
use App\Domains\Executive\Decision\ManagementResponseReadinessPolicy;

final class ExecutiveOsV1AcceptanceCatalog
{
    public static function questions(): array
    {
        return [
            [
                'id' => 'EXE01',
                'question' => 'Does this explicit cross-domain specialist decision set support a bounded human management response now?',
                'policy' => ManagementResponseReadinessPolicy::KEY,
                'answer_shape' => 'Recommended, conditional or deferred human management review guidance preserving the specialist decision boundaries.',
                'contracts' => [
                    [
                        'class' => ExecutiveDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => ExecutiveDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => ExecutiveDecision::class,
                        'property' => 'confidence',
                    ],
                    [
                        'class' => ExecutiveDecision::class,
                        'property' => 'missingTruth',
                    ],
                    [
                        'class' => ExecutiveDecisionContext::class,
                        'property' => 'cfoDecision',
                    ],
                    [
                        'class' => ExecutiveDecisionContext::class,
                        'property' => 'commercialDecision',
                    ],
                    [
                        'class' => ExecutiveDecisionContext::class,
                        'property' => 'deliveryDecision',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            ExecutiveDecision::RECOMMENDED,
            ExecutiveDecision::CONDITIONAL,
            ExecutiveDecision::DEFERRED,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'Which specialist recommendation should management prioritise first?',
            'Choose which specialist decision domains should be consulted.',
            'Merge the specialist recommendations into one new recommendation.',
            'Execute the management response.',
            'Create or persist an Executive action from this decision.',
            'Rank management actions by urgency or score.',
            'Use legacy Executive health or reasoning scores to override specialist truth.',
        ];
    }
}
