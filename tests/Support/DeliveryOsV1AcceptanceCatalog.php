<?php

namespace Tests\Support;

use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;

final class DeliveryOsV1AcceptanceCatalog
{
    /**
     * Canonical delivery-decision questions Delivery OS v1 is allowed
     * to answer from the authoritative recorded-evidence review policy.
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
                'id' => 'DEL01',

                'question' => 'Should recorded client-attributable delivery evidence proceed to human delivery review when one or more WorkLog-backed evidence items exist?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'established_proceed_to_human_review_guidance',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'recommendation',
                    ],
                ],
            ],
            [
                'id' => 'DEL02',

                'question' => 'Can Delivery OS recommend human delivery evidence review when no client-attributable WorkLog-backed delivery evidence is recorded?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'deferred_with_missing_delivery_truth',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'status',
                    ],
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'constraints',
                    ],
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'DEL03',

                'question' => 'Why is Delivery OS giving this delivery-evidence review-readiness guidance?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'rationale_and_evidence',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'rationale',
                    ],
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'DEL04',

                'question' => 'How confident is Delivery OS in an established delivery-evidence review recommendation?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'recommendation_confidence',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'confidence',
                    ],
                ],
            ],
            [
                'id' => 'DEL05',

                'question' => 'What blocks Delivery OS from deciding delivery-evidence review readiness when recorded client-attributable delivery evidence is absent?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'blocking_constraints',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'constraints',
                    ],
                ],
            ],
            [
                'id' => 'DEL06',

                'question' => 'What delivery truth is missing before a deferred delivery-evidence review-readiness decision can be answered?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'missing_truth',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'DEL07',

                'question' => 'Does a recommendation to review recorded delivery evidence establish delivery completion, delivery health, recoverability, commercial disposition or invoice readiness?',

                'policy' => DeliveryEvidenceReviewReadinessPolicy::KEY,

                'answer_shape' => 'explicit_scope_boundary',

                'contracts' => [
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'recommendation',
                    ],
                    [
                        'class' => DeliveryDecision::class,

                        'property' => 'rationale',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            DeliveryDecision::RECOMMENDED,
            DeliveryDecision::DEFERRED,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'Which client should be reviewed first?',
            'Perform the human WorkLog review.',
            'Mark recorded work as invoice, retainer, goodwill, internal or written off.',
            'Draft or send an invoice from this delivery decision.',
            'Decide whether recorded work is commercially recoverable.',
            'Is this client delivery complete or healthy?',
            'Rank projects or delivery priorities.',
        ];
    }
}
