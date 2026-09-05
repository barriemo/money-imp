<?php

namespace Tests\Support;

use App\Domains\Billing\Decision\BillingDecision;
use App\Domains\Billing\Decision\BillingEvidenceConclusionReadinessPolicy;

final class BillingOsV1AcceptanceCatalog
{
    public static function questions(): array
    {
        return [
            [
                'id' => 'BIL01',

                'question' => 'Does canonical billing evidence establish current recurring observed billing for this exact client service?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'current_recurring_observed_billing_guidance',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'BIL02',

                'question' => 'Does the canonical billing read model contain no canonical observed billing for this exact client service without claiming that no billing obligation exists?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'bounded_negative_observed_billing_guidance',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'rationale',
                    ],
                ],
            ],
            [
                'id' => 'BIL03',

                'question' => 'Has canonical billing been observed for this exact client service while recurring billing remains unestablished?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'conditional_non_recurring_observed_billing_guidance',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'constraints',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'BIL04',

                'question' => 'Does recurring canonical billing evidence exist for this exact client service while current recurring billing evidence remains unestablished?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'conditional_non_current_recurring_billing_guidance',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'status',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'constraints',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'missingTruth',
                    ],
                ],
            ],
            [
                'id' => 'BIL05',

                'question' => 'Why is Billing OS giving this exact-client-service billing-evidence conclusion?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'rationale_and_evidence',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'rationale',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'BIL06',

                'question' => 'How confident is Billing OS in the bounded billing-evidence recommendation it has established?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'recommendation_confidence',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'confidence',
                    ],
                ],
            ],
            [
                'id' => 'BIL07',

                'question' => 'Does a recorded current monthly equivalent establish what should be invoiced or the contractual billing obligation for this client service?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'observed_billing_vs_obligation_truth_boundary',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'rationale',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'evidence',
                    ],
                ],
            ],
            [
                'id' => 'BIL08',

                'question' => 'Does Billing OS V1 determine contractual billing obligation, draft or send invoices, write to FreeAgent, rank clients or services, execute billing workflows or persist outcomes?',

                'policy' => BillingEvidenceConclusionReadinessPolicy::KEY,

                'answer_shape' => 'explicit_scope_boundary',

                'contracts' => [
                    [
                        'class' => BillingDecision::class,
                        'property' => 'recommendation',
                    ],
                    [
                        'class' => BillingDecision::class,
                        'property' => 'rationale',
                    ],
                ],
            ],
        ];
    }

    public static function acceptedStatuses(): array
    {
        return [
            BillingDecision::STATUS_RECOMMENDED,
            BillingDecision::STATUS_CONDITIONAL,
        ];
    }

    public static function boundaryQuestions(): array
    {
        return [
            'What amount should we invoice this client service now?',
            'Does no canonical observed billing mean nothing is owed?',
            'Does the current monthly equivalent establish the contractual billing amount?',
            'Create a billing obligation for this client service.',
            'Draft an invoice for this client service.',
            'Send an invoice for this client service.',
            'Run bulk billing.',
            'Write this invoice to FreeAgent.',
            'Which client or service should be billed first?',
            'Rank clients or services by billing priority or urgency.',
            'Execute a billing workflow.',
            'Mutate accounting or commercial truth.',
            'Persist a Billing OS decision outcome.',
        ];
    }
}
