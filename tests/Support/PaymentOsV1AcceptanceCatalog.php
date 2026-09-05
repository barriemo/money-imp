<?php

namespace Tests\Support;

use App\Domains\Payment\Decision\PaymentDecision;
use App\Domains\Payment\Decision\PaymentEvidenceConclusionReadinessPolicy;

final class PaymentOsV1AcceptanceCatalog
{
    /**
     * Canonical Payment OS V1 questions answerable from the one
     * authoritative exact-client payment-evidence conclusion policy.
     */
    public static function questions(): array
    {
        return [
            [
                'id' => 'PAY01',

                'question' => 'Does the available payment evidence support at least one payment candidate for this exact client for bounded human review?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'supported_payment_candidate_guidance',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'PAY02',

                'question' => 'Does the available searched evidence fail to support a payment candidate for this exact client without claiming that no payment occurred?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'bounded_negative_payment_evidence_guidance',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'rationale',
                    ],
                ],
            ],
            [
                'id' => 'PAY03',

                'question' => 'Do weak unidentified exact-amount payment candidates exist while payer identity remains unresolved for this exact client?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'conditional_weak_candidate_guidance',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'constraints',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'PAY04',

                'question' => 'Must the payment-evidence conclusion be deferred because required invoice or bank evidence is missing or incomplete?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'deferred_for_missing_payment_truth',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'constraints',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'PAY05',

                'question' => 'Why is Payment OS giving this exact-client payment-evidence conclusion?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'rationale_and_evidence',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'rationale',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'PAY06',

                'question' => 'How confident is Payment OS in the bounded recommendation it has established from the recorded evidence?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'recommendation_confidence',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'confidence',
                    ],
                ],
            ],
            [
                'id' => 'PAY07',

                'question' => 'Does finding no supported payment candidate establish that this exact client did not pay?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'explicit_no_supported_candidate_truth_boundary',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'rationale',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'PAY08',

                'question' => 'Does Payment OS V1 allocate or approve payments, rank clients, initiate collections or chasing, execute payment workflows or persist outcomes?',

                'policy' => PaymentEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'explicit_scope_boundary',

                'contracts' => [
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => PaymentDecision::class,
                        'property' => 'rationale',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            PaymentDecision::RECOMMENDED,
            PaymentDecision::CONDITIONAL,
            PaymentDecision::DEFERRED,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'Did this client definitely pay?',
            'Did this client definitely not pay?',
            'Allocate this bank transaction to an invoice.',
            'Approve this payment allocation.',
            'Which client should be chased first?',
            'Rank clients by payment risk, priority or urgency.',
            'Start or execute a collections action.',
            'Draft or send an invoice or payment chase.',
            'Mutate accounting or commercial truth.',
            'Persist a Payment OS decision outcome.',
            'Use legacy client risk or attention scoring as authoritative payment truth.',
        ];
    }
}
