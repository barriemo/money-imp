<?php

namespace Tests\Support;

use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\ServiceReconciliationReadinessPolicy;

final class CommercialOsV1AcceptanceCatalog
{
    /**
     * Canonical commercial-decision questions Commercial OS v1 is
     * allowed to answer from the authoritative service-reconciliation
     * readiness policy.
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
                'id' => 'COM01',

                'question' => 'Should an exact commercial evidence set that is review-ready and in the authoritative queue proceed to human service reconciliation now?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'established_proceed_guidance',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'COM02',

                'question' => 'Should a review-ready exact evidence set outside the authoritative queue proceed to service reconciliation now?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'established_do_not_proceed_guidance',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'COM03',

                'question' => 'Can service reconciliation readiness be established when the exact candidate needs more commercial evidence?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'deferred_with_missing_truth',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'constraints',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'COM04',

                'question' => 'Should composite commercial evidence proceed through ordinary human service reconciliation?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'established_separate_commercial_review_guidance',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'COM05',

                'question' => 'Should evidence established as not being a service candidate enter human service reconciliation?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'established_not_service_guidance',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'COM06',

                'question' => 'Why is Commercial OS giving this service-reconciliation readiness guidance?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'rationale_and_evidence',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'rationale',
                    ],
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'COM07',

                'question' => 'How confident is Commercial OS in an established service-reconciliation recommendation?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'recommendation_confidence',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'confidence',
                    ],
                ],
            ],
            [
                'id' => 'COM08',

                'question' => 'What blocks Commercial OS from deciding service-reconciliation readiness when commercial truth is incomplete or unsupported?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'blocking_constraints',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'constraints',
                    ],
                ],
            ],
            [
                'id' => 'COM09',

                'question' => 'What commercial truth is missing before a deferred service-reconciliation readiness decision can be answered?',

                'policy' => ServiceReconciliationReadinessPolicy::KEY,

                'answer_shape' => 'missing_truth',

                'contracts' => [
                    [
                        'class' => CommercialDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            CommercialDecision::RECOMMENDED,
            CommercialDecision::DEFERRED,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'Which commercial candidate should we reconcile first?',
            'Perform the human reconciliation for this candidate.',
            'Create or update the canonical client service from this recommendation.',
            'Send an invoice or chase this client.',
            'Which client should we upsell, retain or contact next?',
            'Should observed invoice history be treated as contracted MRR?',
        ];
    }
}
