<?php

namespace Tests\Support;

use App\Domains\Project\Decision\ProjectDecision;
use App\Domains\Project\Decision\ProjectReviewReadinessPolicy;

final class ProjectOsV1AcceptanceCatalog
{
    /**
     * Canonical Project OS V1 questions answerable from the one
     * authoritative exact-project human-review readiness policy.
     */
    public static function questions(): array
    {
        return [
            [
                'id' => 'PROJ01',

                'question' => 'Should this exact project proceed to human project review when an explicit Project OS V1 review signal is recorded and no V1 evidence condition remains unresolved?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'established_human_project_review_guidance',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'PROJ02',

                'question' => 'Should this exact project proceed to human project review when a review signal is recorded but V1 project evidence remains unresolved?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'conditional_human_review_with_explicit_uncertainty',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'constraints',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'PROJ03',

                'question' => 'Can Project OS conclude that this exact project does not need human review when no affirmative V1 review signal is recorded?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'deferred_without_negative_inference',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'PROJ04',

                'question' => 'Why is Project OS giving this exact-project review-readiness guidance?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'rationale_and_evidence',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'rationale',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'PROJ05',

                'question' => 'How confident is Project OS in an established or conditional exact-project human-review recommendation?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'recommendation_confidence',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'confidence',
                    ],
                ],
            ],
            [
                'id' => 'PROJ06',

                'question' => 'What unresolved Project OS V1 evidence conditions must remain explicit during human project review?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'conditions_and_missing_truth',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'constraints',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'PROJ07',

                'question' => 'Does the absence of a recorded Project OS V1 review signal establish that this project is healthy, complete, on track or exempt from review?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'explicit_no_signal_truth_boundary',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'rationale',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'PROJ08',

                'question' => 'Does Project OS V1 human-review guidance classify project health, rank projects, create actions, execute work or persist outcomes?',

                'policy' => ProjectReviewReadinessPolicy::KEY,

                'answer_shape' => 'explicit_scope_boundary',

                'contracts' => [
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'recommendation',
                    ],
                    [
                        'class' => ProjectDecision::class,

                        'property' => 'rationale',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            ProjectDecision::RECOMMENDED,
            ProjectDecision::CONDITIONAL,
            ProjectDecision::DEFERRED,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'Is this project healthy?',
            'Which project should be reviewed first?',
            'Rank projects by priority, risk or urgency.',
            'Create or assign a project action.',
            'Execute project work or remediation.',
            'Change project health or lifecycle status.',
            'Persist a project decision outcome.',
            'Use legacy Project Brain scoring or recommendations as authoritative truth.',
        ];
    }
}
