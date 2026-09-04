<?php

namespace Tests\Support;

use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;

final class CfoV1AcceptanceCatalog
{
    /**
     * Canonical financial-decision questions CFO Imp v1 is allowed
     * to answer from the authoritative discretionary-spend policy.
     *
     * @return array<int, array{
     *     id: string,
     *     question: string,
     *     policy: string,
     *     answer_shape: string,
     *     contracts: array<int, array{
     *         class: class-string,
     *         property: string
     *     }>
     * }>
     */
    public static function questions(): array
    {
        return [
            [
                'id' => 'CFO01',

                'question' => 'Can we support a one-off discretionary spend that is within established safe available cash?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'established_guidance',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CfoDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'CFO02',

                'question' => 'Should we avoid a one-off discretionary spend that exceeds established safe available cash?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'established_do_not_spend_guidance',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CfoDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'CFO03',

                'question' => 'Why is the CFO giving that discretionary-spend guidance?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'rationale_and_evidence',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'rationale',
                    ],
                    [
                        'class' => CfoDecision::class,

                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'CFO04',

                'question' => 'How confident is the CFO in the discretionary-spend recommendation?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'recommendation_confidence',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'confidence',
                    ],
                ],
            ],
            [
                'id' => 'CFO05',

                'question' => 'What blocks the CFO from recommending a spend when safe available cash is unknown?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'blocking_constraints',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'constraints',
                    ],
                ],
            ],
            [
                'id' => 'CFO06',

                'question' => 'What truth is missing before the CFO can answer the discretionary-spend question?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'missing_truth',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'CFO07',

                'question' => 'Can current safe cash alone establish whether a recurring discretionary commitment is supportable?',

                'policy' => DiscretionarySpendDecisionPolicy::KEY,

                'answer_shape' => 'deferred_with_forward_truth_blocker',

                'contracts' => [
                    [
                        'class' => CfoDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CfoDecision::class,

                        'property' => 'constraints',
                    ],
                    [
                        'class' => CfoDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            CfoDecision::RECOMMENDED,
            CfoDecision::DEFERRED,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'Which CFO recommendation should we prioritise next?',
            'Execute this recommendation.',
            'What will our cash position be next month?',
            'Can we hire another employee?',
            'Should we take additional credit?',
        ];
    }
}
