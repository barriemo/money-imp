<?php

namespace Tests\Support;

use App\Domains\BusinessBrain\BusinessState\BusinessStateProjection;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChange;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateChangeReport;
use App\Domains\BusinessBrain\BusinessState\Change\BusinessStateMetricCatalog;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanation;
use App\Domains\BusinessBrain\BusinessState\Explanation\BusinessStateExplanationReport;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;

final class BusinessBrainV1AcceptanceCatalog
{
    public const STATE =
        'state';

    public const CHANGE =
        'change';

    public const ATTENTION =
        'attention';

    public const EXPLANATION =
        'explanation';

    public const LAYERS = [
        self::STATE,
        self::CHANGE,
        self::ATTENTION,
        self::EXPLANATION,
    ];

    /**
     * Canonical executive questions Business Brain v1 must be able
     * to answer without recommendation or decision logic.
     *
     * @return array<int, array{
     *     id: string,
     *     question: string,
     *     layer: string,
     *     answer_shape: string,
     *     contracts: array<int, array{
     *         class: class-string,
     *         property: string
     *     }>,
     *     metrics: array<int, string>
     * }>
     */
    public static function questions(): array
    {
        return [
            [
                'id' => 'Q01',

                'question' => 'What cash is actually verified right now?',

                'layer' => self::STATE,

                'answer_shape' => 'amount_and_evidence_coverage',

                'contracts' => [
                    [
                        'class' => CashTruth::class,

                        'property' => 'verifiedCash',
                    ],
                    [
                        'class' => CashTruth::class,

                        'property' => 'verifiedAccountCount',
                    ],
                    [
                        'class' => CashTruth::class,

                        'property' => 'accountCount',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS,
                ],
            ],

            [
                'id' => 'Q02',

                'question' => 'How much cash can we safely say is available?',

                'layer' => self::STATE,

                'answer_shape' => 'known_or_unknown_amount',

                'contracts' => [
                    [
                        'class' => CashTruth::class,

                        'property' => 'safeAvailableCash',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::SAFE_AVAILABLE_CASH,
                ],
            ],

            [
                'id' => 'Q03',

                'question' => 'What is our known net position?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => CashTruth::class,

                        'property' => 'knownNetPosition',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::KNOWN_NET_POSITION,
                ],
            ],

            [
                'id' => 'Q04',

                'question' => 'How much is outstanding in receivables?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => ReceivablesPosition::class,

                        'property' => 'ledgerOutstanding',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::LEDGER_OUTSTANDING_RECEIVABLES,
                ],
            ],

            [
                'id' => 'Q05',

                'question' => 'How much money is waiting allocation?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => ReceivablesPosition::class,

                        'property' => 'paymentsWaitingAllocation',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::PAYMENTS_WAITING_ALLOCATION,
                ],
            ],

            [
                'id' => 'Q06',

                'question' => 'How much of our receivables is verified collectible?',

                'layer' => self::STATE,

                'answer_shape' => 'known_or_unknown_amount',

                'contracts' => [
                    [
                        'class' => ReceivablesPosition::class,

                        'property' => 'verifiedCollectible',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::VERIFIED_COLLECTIBLE_RECEIVABLES,
                ],
            ],

            [
                'id' => 'Q07',

                'question' => 'What liability exposure is currently known?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => LiabilityPosition::class,

                        'property' => 'known',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::KNOWN_LIABILITY_EXPOSURE,
                ],
            ],

            [
                'id' => 'Q08',

                'question' => 'Do we know our total liability exposure, and what is missing if not?',

                'layer' => self::STATE,

                'answer_shape' => 'knownness_and_missing_truth',

                'contracts' => [
                    [
                        'class' => LiabilityPosition::class,

                        'property' => 'coverageComplete',
                    ],
                    [
                        'class' => LiabilityPosition::class,

                        'property' => 'unknownCategories',
                    ],
                    [
                        'class' => BusinessStateProjection::class,

                        'property' => 'unknowns',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::TOTAL_LIABILITY_EXPOSURE,
                ],
            ],

            [
                'id' => 'Q09',

                'question' => 'What active credit facilities and verified exposure are recorded?',

                'layer' => self::STATE,

                'answer_shape' => 'count_and_amount',

                'contracts' => [
                    [
                        'class' => CreditTruth::class,

                        'property' => 'facilityCount',
                    ],
                    [
                        'class' => CreditTruth::class,

                        'property' => 'verifiedFacilityCount',
                    ],
                    [
                        'class' => CreditTruth::class,

                        'property' => 'verifiedExposure',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q10',

                'question' => 'How many client records are marked active?',

                'layer' => self::STATE,

                'answer_shape' => 'count',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'clientCount',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::CLIENT_RECORDS_MARKED_ACTIVE,
                ],
            ],

            [
                'id' => 'Q11',

                'question' => 'How much gross invoiced revenue do those records represent?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'grossInvoiced',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::GROSS_INVOICED_REVENUE_REPRESENTED,
                ],
            ],

            [
                'id' => 'Q12',

                'question' => 'How much revenue does accounting record as paid?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'paidAccordingToAccounting',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::PAID_REVENUE_ACCORDING_TO_ACCOUNTING,
                ],
            ],

            [
                'id' => 'Q13',

                'question' => 'How much invoiced revenue remains outstanding?',

                'layer' => self::STATE,

                'answer_shape' => 'amount',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'outstanding',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::OUTSTANDING_INVOICED_REVENUE,
                ],
            ],

            [
                'id' => 'Q14',

                'question' => 'How much approved bank-backed payment evidence do we have?',

                'layer' => self::STATE,

                'answer_shape' => 'evidence_amount',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'bankVerifiedPaymentValue',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::APPROVED_BANK_BACKED_PAYMENT_EVIDENCE,
                ],
            ],

            [
                'id' => 'Q15',

                'question' => 'How many client records have outstanding revenue?',

                'layer' => self::STATE,

                'answer_shape' => 'count',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'clientsWithOutstandingRevenue',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_OUTSTANDING_REVENUE,
                ],
            ],

            [
                'id' => 'Q16',

                'question' => 'Which client records have the largest recorded outstanding balances?',

                'layer' => self::STATE,

                'answer_shape' => 'ranked_recorded_condition',

                'contracts' => [
                    [
                        'class' => BusinessStateProjection::class,

                        'property' => 'commercialConditions',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q17',

                'question' => 'How many client records have weak payment evidence?',

                'layer' => self::STATE,

                'answer_shape' => 'count',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'clientsWithWeakPaymentEvidence',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::CLIENT_RECORDS_WITH_WEAK_PAYMENT_EVIDENCE,
                ],
            ],

            [
                'id' => 'Q18',

                'question' => 'How complete is our work-log evidence?',

                'layer' => self::STATE,

                'answer_shape' => 'evidence_coverage',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'clientsWithoutWorkEvidence',
                    ],
                    [
                        'class' => BusinessStateProjection::class,

                        'property' => 'workFacts',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::CLIENT_RECORDS_WITHOUT_WORK_EVIDENCE,
                ],
            ],

            [
                'id' => 'Q19',

                'question' => 'How much unrecovered work is established from recorded work?',

                'layer' => self::STATE,

                'answer_shape' => 'evidence_bounded_amount',

                'contracts' => [
                    [
                        'class' => RevenueTruthSummary::class,

                        'property' => 'unrecoveredWorkValue',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::RECORDED_UNRECOVERED_WORK_VALUE,
                ],
            ],

            [
                'id' => 'Q20',

                'question' => 'How complete and current is our bank-account evidence?',

                'layer' => self::STATE,

                'answer_shape' => 'evidence_coverage',

                'contracts' => [
                    [
                        'class' => CashTruth::class,

                        'property' => 'verifiedAccountCount',
                    ],
                    [
                        'class' => CashTruth::class,

                        'property' => 'unverifiedAccountCount',
                    ],
                    [
                        'class' => CashTruth::class,

                        'property' => 'staleAccountCount',
                    ],
                ],

                'metrics' => [
                    BusinessStateMetricCatalog::VERIFIED_BANK_ACCOUNT_RECORDS,
                    BusinessStateMetricCatalog::UNVERIFIED_BANK_ACCOUNT_RECORDS,
                    BusinessStateMetricCatalog::STALE_BANK_ACCOUNT_RECORDS,
                ],
            ],

            [
                'id' => 'Q21',

                'question' => 'What important business truth is currently unknown?',

                'layer' => self::STATE,

                'answer_shape' => 'explicit_unknowns',

                'contracts' => [
                    [
                        'class' => BusinessStateProjection::class,

                        'property' => 'unknowns',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q22',

                'question' => 'Where is our evidence coverage incomplete?',

                'layer' => self::STATE,

                'answer_shape' => 'evidence_gaps',

                'contracts' => [
                    [
                        'class' => BusinessStateProjection::class,

                        'property' => 'evidenceGaps',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q23',

                'question' => 'What has changed since the previous captured business state?',

                'layer' => self::CHANGE,

                'answer_shape' => 'temporal_changes',

                'contracts' => [
                    [
                        'class' => BusinessStateChangeReport::class,

                        'property' => 'changes',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q24',

                'question' => 'What has become known or unknown?',

                'layer' => self::CHANGE,

                'answer_shape' => 'knownness_changes',

                'contracts' => [
                    [
                        'class' => BusinessStateChange::class,

                        'property' => 'kind',
                    ],
                    [
                        'class' => BusinessStateChange::class,

                        'property' => 'previous',
                    ],
                    [
                        'class' => BusinessStateChange::class,

                        'property' => 'current',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q25',

                'question' => 'What change currently deserves attention?',

                'layer' => self::ATTENTION,

                'answer_shape' => 'deterministic_attention',

                'contracts' => [
                    [
                        'class' => BusinessStateChangeReport::class,

                        'property' => 'attention',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q26',

                'question' => 'What do we actually know about why a recorded change happened?',

                'layer' => self::EXPLANATION,

                'answer_shape' => 'evidence_backed_interpretation',

                'contracts' => [
                    [
                        'class' => BusinessStateExplanationReport::class,

                        'property' => 'explanations',
                    ],
                    [
                        'class' => BusinessStateExplanation::class,

                        'property' => 'interpretation',
                    ],
                    [
                        'class' => BusinessStateExplanation::class,

                        'property' => 'evidence',
                    ],
                    [
                        'class' => BusinessStateExplanation::class,

                        'property' => 'status',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q27',

                'question' => 'If we do not know why, what truth is missing?',

                'layer' => self::EXPLANATION,

                'answer_shape' => 'missing_truth',

                'contracts' => [
                    [
                        'class' => BusinessStateExplanation::class,

                        'property' => 'missingTruth',
                    ],
                ],

                'metrics' => [],
            ],

            [
                'id' => 'Q28',

                'question' => 'How confident are we in the explanation?',

                'layer' => self::EXPLANATION,

                'answer_shape' => 'interpretation_confidence',

                'contracts' => [
                    [
                        'class' => BusinessStateExplanation::class,

                        'property' => 'confidence',
                    ],
                ],

                'metrics' => [],
            ],
        ];
    }

    /**
     * Questions intentionally outside Business Brain v1.
     *
     * @return array<int, string>
     */
    public static function decisionBoundaryQuestions(): array
    {
        return [
            'What should we do next?',
            'Which action should we prioritise?',
        ];
    }
}
