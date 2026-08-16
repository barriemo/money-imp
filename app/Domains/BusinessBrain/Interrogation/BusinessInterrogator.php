<?php

namespace App\Domains\BusinessBrain\Interrogation;

use App\Domains\BusinessBrain\Actions\ExecutiveActionService;
use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\CashTruth\CashTruthService;
use App\Domains\BusinessBrain\Decisions\BusinessDecisionService;
use App\Domains\BusinessBrain\Decisions\Outcomes\BusinessDecisionOutcomeService;
use App\Domains\BusinessBrain\Evidence\ClientPaymentEvidenceSummaryService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\BusinessBrain\Interrogation\Attention\ClientAttentionService;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessCoverageSummaryService;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverageService;
use App\Domains\BusinessBrain\Interrogation\Position\BusinessPositionService;
use App\Domains\BusinessBrain\Learning\ActionOutcomeProfileService;
use App\Domains\BusinessBrain\Learning\LearningConfidenceService;
use App\Domains\BusinessBrain\MorningBrief\Services\MorningBriefService;
use App\Domains\BusinessBrain\Observations\BusinessObservationService;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoningSummaryService;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummaryService;
use App\Domains\BusinessBrain\Timeline\ClientTimelineBuilder;
use App\Models\Client;
use InvalidArgumentException;

class BusinessInterrogator
{
    public function __construct(
        private MorningBriefService $morningBrief,

        private BusinessTruthCoverageService $coverage,

        private BusinessPositionService $position,

        private ClientAttentionService $attention,

        private BusinessCoverageSummaryService $coverageSummary,

        private BusinessDecisionService $decisions,

        private BusinessObservationService $observations,

        private BusinessDecisionOutcomeService $decisionOutcomes,

        private ClientTimelineBuilder $timeline,

        private ClientPaymentEvidenceSummaryService $paymentEvidence,

        private RevenueTruthSummaryService $revenueTruth,

        private ExecutiveReasoningSummaryService $executiveReasoning,

        private ActionOutcomeProfileService $outcomeProfiles,

        private LearningConfidenceService $learningConfidence,

        private ExecutiveActionService $executiveActions,

        private CashTruthService $cashTruth,

        private FinancialPositionService $financialPosition
    ) {}

    public function ask(
        BusinessQuestion $question,
        ?AttentionContext $context = null
    ): BusinessAnswer {
        $normalised =
            $question->normalised();

        if (
            in_array(
                $normalised,
                [
                    'where are we?',
                    'where are we',
                ],
                true
            )
        ) {
            return $this->whereAreWe(
                $question
            );
        }

        if (
            str_starts_with(
                $normalised,
                'what do you know about '
            )
        ) {
            return $this->whatDoYouKnowAbout(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'which clients need attention today?',
                    'which clients need attention today',
                    'who needs attention today?',
                    'who needs attention today',
                ],
                true
            )
        ) {
            return $this->clientsNeedingAttention(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'where are we leaking revenue?',
                    'where are we leaking revenue',
                    'where is revenue leaking?',
                    'where is revenue leaking',
                    'what revenue are we leaking?',
                    'what revenue are we leaking',
                ],
                true
            )
        ) {
            return $this->revenueLeakage(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'where are we losing money?',
                    'where are we losing money',
                    'where is money leaking?',
                    'where is money leaking',
                ],
                true
            )
        ) {
            return $this->moneyAtRisk(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what don\'t you know yet?',
                    'what don\'t you know yet',
                    'what do you not know yet?',
                    'what do you not know yet',
                    'what are your blind spots?',
                    'what are your blind spots',
                ],
                true
            )
        ) {
            return $this->unknowns(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what should i do today?',
                    'what should i do today',
                    'what should we do today?',
                    'what should we do today',
                    'what should i do next?',
                    'what should i do next',
                ],
                true
            )
        ) {
            return $this->todayDecisions(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what changed?',
                    'what changed',
                    'what changed since yesterday?',
                    'what changed since yesterday',
                ],
                true
            )
        ) {
            return $this->whatChanged(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what happened to our recommendations?',
                    'what happened to our recommendations',
                    'how have our recommendations performed?',
                    'how have our recommendations performed',
                    'what happened to our decisions?',
                    'what happened to our decisions',
                ],
                true
            )
        ) {
            return $this->recommendationOutcomes(
                $question
            );
        }

        if (
            str_starts_with(
                $normalised,
                'what happened with '
            )
            ||
            str_starts_with(
                $normalised,
                'what happened to '
            )
        ) {
            return $this->clientHistory(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what are today\'s biggest opportunities?',
                    'what are today\'s biggest opportunities',
                    'where can we make the most money today?',
                    'where can we make the most money today',
                    'what is my highest roi action?',
                    'what is my highest roi action',
                    'what\'s my highest roi action?',
                    'what\'s my highest roi action',
                ],
                true
            )
        ) {
            return $this->executiveOpportunities(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what have you learned?',
                    'what have you learned',
                    'what strategies are working?',
                    'what strategies are working',
                    'what is working?',
                    'what is working',
                ],
                true
            )
        ) {
            return $this->learningSummary(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what are we waiting on?',
                    'what are we waiting on',
                    'what am i waiting on?',
                    'what am i waiting on',
                    'what is waiting?',
                    'what is waiting',
                ],
                true
            )
        ) {
            return $this->waitingActions(
                $question
            );
        }

        if (
            in_array(
                $normalised,
                [
                    'what is our cash position?',
                    'what is our cash position',
                    'what\'s our cash position?',
                    'what\'s our cash position',
                    'how much cash do we have?',
                    'how much cash do we have',
                ],
                true
            )
        ) {
            return $this->cashPosition(
                $question
            );
        }

        throw new InvalidArgumentException(
            'Unsupported business question: '.$question->question
        );
    }

    private function whatDoYouKnowAbout(
        BusinessQuestion $question
    ): BusinessAnswer {
        $name =
            trim(
                substr(
                    $question->question,
                    strlen(
                        'what do you know about '
                    )
                ),
                ' ?'
            );

        $client =
            Client::query()
                ->where(
                    'name',
                    'like',
                    '%'.$name.'%'
                )
                ->first();

        if (! $client) {
            return new BusinessAnswer(
                question: $question->question,

                answer: sprintf(
                    'I could not find a client matching "%s".',
                    $name
                ),

                facts: [
                    'client_found' => false,
                ],

                evidence: [],

                confidence: 0,

                asOf: now()
            );
        }

        $coverage =
            $this->coverage->forClient(
                $client
            );

        $known = collect([
            'invoices' => $coverage->invoiceCount,
            'bank_transactions' => $coverage->bankTransactionCount,
            'payment_identities' => $coverage->paymentIdentityCount,
            'work_logs' => $coverage->workLogCount,
            'services' => $coverage->serviceCount,
            'charlie_findings' => $coverage->openCharlieFindingCount,
        ]);

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                '%s has %d invoices, %d bank transactions, %d payment identities, %d work logs, %d services and %d open Charlie findings. Truth coverage confidence is %d%%.',
                $coverage->client,
                $coverage->invoiceCount,
                $coverage->bankTransactionCount,
                $coverage->paymentIdentityCount,
                $coverage->workLogCount,
                $coverage->serviceCount,
                $coverage->openCharlieFindingCount,
                $coverage->confidence
            ),

            facts: [
                'client' => $coverage->client,

                'invoice_count' => $coverage->invoiceCount,

                'bank_transaction_count' => $coverage->bankTransactionCount,

                'payment_identity_count' => $coverage->paymentIdentityCount,

                'work_log_count' => $coverage->workLogCount,

                'service_count' => $coverage->serviceCount,

                'open_charlie_finding_count' => $coverage->openCharlieFindingCount,

                'coverage_confidence' => $coverage->confidence,
            ],

            evidence: $known
                ->map(
                    fn (int $count, string $type) => [
                        'type' => $type,

                        'count' => $count,

                        'known' => $count > 0,
                    ]
                )
                ->values()
                ->all(),

            confidence: $coverage->confidence,

            asOf: now()
        );
    }

    private function whereAreWe(
        BusinessQuestion $question
    ): BusinessAnswer {
        $position =
            $this->position->current();

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Money Imp currently knows about %d active clients, %d invoices worth £%s gross, £%s outstanding, %d bank transactions, %d unmatched bank transactions and %d open Charlie findings across %d clients.',
                $position->clientCount,
                $position->invoiceCount,
                number_format(
                    $position->grossInvoiced,
                    2
                ),
                number_format(
                    $position->outstanding,
                    2
                ),
                $position->bankTransactionCount,
                $position->unmatchedBankTransactionCount,
                $position->openCharlieFindingCount,
                $position->clientsWithOpenCharlieFindings
            ),

            facts: [
                'active_clients' => $position->clientCount,

                'invoice_count' => $position->invoiceCount,

                'gross_invoiced' => $position->grossInvoiced,

                'outstanding' => $position->outstanding,

                'bank_transaction_count' => $position->bankTransactionCount,

                'unmatched_bank_transactions' => $position->unmatchedBankTransactionCount,

                'open_charlie_findings' => $position->openCharlieFindingCount,

                'clients_with_open_charlie_findings' => $position->clientsWithOpenCharlieFindings,
            ],

            evidence: [
                [
                    'source' => 'clients',
                    'records' => $position->clientCount,
                ],
                [
                    'source' => 'accounting_invoices',
                    'records' => $position->invoiceCount,
                ],
                [
                    'source' => 'bank_transactions',
                    'records' => $position->bankTransactionCount,
                ],
                [
                    'source' => 'charlie_findings',
                    'records' => $position->openCharlieFindingCount,
                ],
            ],

            confidence: 100,

            asOf: now()
        );
    }

    private function clientsNeedingAttention(
        BusinessQuestion $question
    ): BusinessAnswer {
        $positions =
            $this->attention
                ->ranked()
                ->take(5);

        if ($positions->isEmpty()) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'No clients currently require attention.',

                facts: [
                    'client_count' => 0,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        $lines =
            $positions
                ->values()
                ->map(
                    function ($position, int $index): string {
                        $reasons =
                            collect(
                                $position->reasons
                            )
                                ->map(
                                    fn (string $reason) => '   - '.$reason
                                )
                                ->implode(
                                    PHP_EOL
                                );

                        return sprintf(
                            '%d. %s%s%s',
                            $index + 1,
                            $position->client,
                            PHP_EOL,
                            $reasons
                        );
                    }
                );

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Clients needing attention today:%s%s',
                PHP_EOL.PHP_EOL,
                $lines->implode(
                    PHP_EOL.PHP_EOL
                )
            ),

            facts: [
                'client_count' => $positions->count(),

                'highest_priority_client' => $positions
                    ->first()
                    ->client,
            ],

            evidence: $positions
                ->map(
                    fn ($position) => [
                        'client_id' => $position->clientId,

                        'client' => $position->client,

                        'score' => $position->score,

                        'overdue' => $position->overdue,

                        'days_since_last_invoice' => $position
                            ->daysSinceLastInvoice,

                        'high_priority_findings' => $position
                            ->highPriorityFindings,

                        'reasons' => $position->reasons,
                    ]
                )
                ->values()
                ->all(),

            confidence: 95,

            asOf: now()
        );
    }

    private function revenueLeakage(
        BusinessQuestion $question
    ): BusinessAnswer {
        $summary =
            $this->revenueTruth
                ->current();

        $topOutstanding =
            $summary
                ->gaps
                ->where(
                    'type',
                    'outstanding_revenue'
                )
                ->sortByDesc(
                    'value'
                )
                ->take(5)
                ->values();

        $evidence =
            $topOutstanding
                ->map(
                    fn ($gap) => [
                        'client_id' => $gap->clientId,

                        'client' => $gap->client,

                        'type' => $gap->type,

                        'value' => $gap->value,

                        'description' => $gap->description,

                        'priority' => $gap->priority,

                        'confidence' => $gap->confidence,
                    ]
                )
                ->values()
                ->all();

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Money Imp can currently prove £%s of outstanding invoiced revenue across %d clients. Accounting records £%s as paid, but only £%s is currently backed by approved bank-allocation evidence. %d clients have weak payment evidence and %d clients have no work-log evidence. Proven unbilled delivery leakage is currently £%s, but this figure is incomplete because delivery evidence is missing. Average commercial confidence is %d%%.',
                number_format(
                    $summary->outstanding,
                    2
                ),
                $summary->clientsWithOutstandingRevenue,
                number_format(
                    $summary->paidAccordingToAccounting,
                    2
                ),
                number_format(
                    $summary->bankVerifiedPaymentValue,
                    2
                ),
                $summary->clientsWithWeakPaymentEvidence,
                $summary->clientsWithoutWorkEvidence,
                number_format(
                    $summary->unrecoveredWorkValue,
                    2
                ),
                $summary->averageCommercialConfidence
            ),

            facts: [
                'client_count' => $summary->clientCount,

                'gross_invoiced' => $summary->grossInvoiced,

                'paid_according_to_accounting' => $summary
                    ->paidAccordingToAccounting,

                'outstanding_revenue' => $summary->outstanding,

                'proven_unbilled_delivery_value' => $summary
                    ->unrecoveredWorkValue,

                'bank_verified_payment_value' => $summary
                    ->bankVerifiedPaymentValue,

                'clients_with_outstanding_revenue' => $summary
                    ->clientsWithOutstandingRevenue,

                'clients_with_weak_payment_evidence' => $summary
                    ->clientsWithWeakPaymentEvidence,

                'clients_without_work_evidence' => $summary
                    ->clientsWithoutWorkEvidence,

                'average_commercial_confidence' => $summary
                    ->averageCommercialConfidence,

                'delivery_leakage_complete' => $summary
                    ->clientsWithoutWorkEvidence === 0,
            ],

            evidence: $evidence,

            confidence: $summary
                ->averageCommercialConfidence,

            asOf: now()
        );
    }

    private function moneyAtRisk(
        BusinessQuestion $question
    ): BusinessAnswer {
        $positions =
            $this->attention
                ->ranked();

        $overdue =
            (float) $positions
                ->sum(
                    'overdue'
                );

        $dormant =
            $positions
                ->where(
                    'billingDormant',
                    true
                );

        $topDebtors =
            $positions
                ->filter(
                    fn ($position) => $position->overdue > 0
                )
                ->sortByDesc(
                    'overdue'
                )
                ->take(5)
                ->values();

        $evidence =
            $topDebtors
                ->map(
                    fn ($position) => [
                        'client_id' => $position->clientId,

                        'client' => $position->client,

                        'type' => 'overdue_receivable',

                        'value' => $position->overdue,

                        'reasons' => $position->reasons,
                    ]
                )
                ->values()
                ->all();

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Money Imp can currently identify £%s of overdue receivables across the attention set and %d dormant client relationships. These are commercial risks, not confirmed losses. Proven delivery leakage cannot yet be calculated reliably because work-log and service coverage is incomplete.',
                number_format(
                    $overdue,
                    2
                ),
                $dormant->count()
            ),

            facts: [
                'overdue_receivables' => $overdue,

                'dormant_clients' => $dormant->count(),

                'top_debtor_count' => $topDebtors->count(),

                'proven_delivery_leakage' => null,
            ],

            evidence: $evidence,

            confidence: 85,

            asOf: now()
        );
    }

    private function unknowns(
        BusinessQuestion $question
    ): BusinessAnswer {
        $summary =
            $this->coverageSummary
                ->current();

        $gaps = [
            'clients_without_invoices' => $summary
                ->clientsWithoutInvoices,

            'clients_without_bank_transactions' => $summary
                ->clientsWithoutBankTransactions,

            'clients_without_payment_identities' => $summary
                ->clientsWithoutPaymentIdentities,

            'clients_without_work_logs' => $summary
                ->clientsWithoutWorkLogs,

            'clients_without_services' => $summary
                ->clientsWithoutServices,

            'clients_without_charlie_findings' => $summary
                ->clientsWithoutCharlieFindings,
        ];

        $largestGap =
            collect(
                $gaps
            )
                ->sortDesc()
                ->keys()
                ->first();

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Across %d active clients, average truth coverage is %d%%. Money Imp currently lacks work-log evidence for %d clients and service definitions for %d clients. These gaps limit reliable delivery profitability and proven revenue-leakage analysis.',
                $summary->clientCount,
                $summary->averageCoverageConfidence,
                $summary->clientsWithoutWorkLogs,
                $summary->clientsWithoutServices
            ),

            facts: [
                'client_count' => $summary->clientCount,

                ...$gaps,

                'average_coverage_confidence' => $summary
                    ->averageCoverageConfidence,

                'largest_gap' => $largestGap,
            ],

            evidence: collect(
                $gaps
            )
                ->map(
                    fn (int $count, string $type) => [
                        'type' => $type,

                        'affected_clients' => $count,
                    ]
                )
                ->values()
                ->all(),

            confidence: 100,

            asOf: now()
        );
    }

    private function todayDecisions(
        BusinessQuestion $question
    ): BusinessAnswer {
        $allDecisions =
            $this->decisions
                ->today();

        $decisions =
            collect()
                ->merge(
                    $allDecisions
                        ->where(
                            'type',
                            'collections'
                        )
                        ->take(3)
                )
                ->merge(
                    $allDecisions
                        ->where(
                            'type',
                            'billing_dormancy'
                        )
                        ->reject(
                            fn ($decision) => $allDecisions
                                ->where(
                                    'type',
                                    'collections'
                                )
                                ->take(3)
                                ->contains(
                                    'clientId',
                                    $decision->clientId
                                )
                        )
                        ->take(1)
                )
                ->merge(
                    $allDecisions
                        ->where(
                            'type',
                            'charlie_follow_up'
                        )
                        ->take(1)
                )
                ->sortByDesc(
                    'priority'
                )
                ->values();

        $this->decisionOutcomes
            ->recordToday(
                $decisions
            );

        if ($decisions->isEmpty()) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'There are no current business actions requiring attention.',

                facts: [
                    'decision_count' => 0,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        $lines =
            $decisions
                ->map(
                    fn ($decision, int $index) => sprintf(
                        '%d. %s - %s %s',
                        $index + 1,
                        $decision->client,
                        $decision->action,
                        $decision->reason
                    )
                );

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                "Today's recommended actions:%s%s",
                PHP_EOL.PHP_EOL,
                $lines->implode(
                    PHP_EOL
                )
            ),

            facts: [
                'decision_count' => $decisions->count(),

                'highest_priority_client' => $decisions
                    ->first()
                    ->client,

                'highest_priority_type' => $decisions
                    ->first()
                    ->type,

                'highest_priority' => $decisions
                    ->first()
                    ->priority,
            ],

            evidence: $decisions
                ->map(
                    fn ($decision) => [
                        'type' => $decision->type,

                        'client_id' => $decision->clientId,

                        'client' => $decision->client,

                        'action' => $decision->action,

                        'reason' => $decision->reason,

                        'priority' => $decision->priority,

                        'value' => $decision->value,

                        'confidence' => $decision->confidence,
                    ]
                )
                ->all(),

            confidence: 95,

            asOf: now()
        );
    }

    private function describeObservationChange(
        $change
    ): string {
        $current =
            $change->observation;

        $previous =
            $change->previous;

        if (
            in_array(
                $change->type,
                [
                    'improved',
                    'worsened',
                ],
                true
            )
            && $previous
            && $previous->value !== null
            && $current->value !== null
        ) {
            $difference =
                abs(
                    $current->value
                    - $previous->value
                );

            return sprintf(
                '%s: %s changed from £%s to £%s, %s £%s.',
                strtoupper(
                    $change->type
                ),
                $current->client
                    ?? $current->title,
                number_format(
                    $previous->value,
                    2
                ),
                number_format(
                    $current->value,
                    2
                ),
                $change->type === 'worsened'
                    ? 'up'
                    : 'down',
                number_format(
                    $difference,
                    2
                )
            );
        }

        return sprintf(
            '%s: %s',
            strtoupper(
                $change->type
            ),
            $current->title
        );
    }

    private function whatChanged(
        BusinessQuestion $question
    ): BusinessAnswer {
        $hadPreviousSnapshot =
            $this->observations
                ->hasSnapshot();

        $changes =
            $this->observations
                ->observe();

        if (! $hadPreviousSnapshot) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'Baseline business observation snapshot created. Future observations can now be compared against this position.',

                facts: [
                    'change_count' => 0,

                    'baseline_created' => true,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        if ($changes->isEmpty()) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'No material business changes were detected since the previous observation snapshot.',

                facts: [
                    'change_count' => 0,

                    'baseline_created' => false,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        $lines =
            $changes
                ->take(10)
                ->map(
                    fn ($change, int $index) => sprintf(
                        '%d. %s',
                        $index + 1,
                        $this->describeObservationChange(
                            $change
                        )
                    )
                );

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Material business changes:%s%s',
                PHP_EOL.PHP_EOL,
                $lines->implode(
                    PHP_EOL
                )
            ),

            facts: [
                'change_count' => $changes->count(),

                'new_count' => $changes
                    ->where(
                        'type',
                        'new'
                    )
                    ->count(),

                'worsened_count' => $changes
                    ->where(
                        'type',
                        'worsened'
                    )
                    ->count(),

                'improved_count' => $changes
                    ->where(
                        'type',
                        'improved'
                    )
                    ->count(),

                'resolved_count' => $changes
                    ->where(
                        'type',
                        'resolved'
                    )
                    ->count(),
            ],

            evidence: $changes
                ->map(
                    fn ($change) => [
                        'change_type' => $change->type,

                        'observation_type' => $change
                            ->observation
                            ->type,

                        'client' => $change
                            ->observation
                            ->client,

                        'title' => $change
                            ->observation
                            ->title,

                        'value' => $change
                            ->observation
                            ->value,

                        'priority' => $change
                            ->observation
                            ->priority,
                    ]
                )
                ->values()
                ->all(),

            confidence: 95,

            asOf: now()
        );
    }

    private function recommendationOutcomes(
        BusinessQuestion $question
    ): BusinessAnswer {
        $summary =
            $this->decisionOutcomes
                ->summary();

        if ($summary['total'] === 0) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'No recommendation outcomes have been recorded yet. Money Imp can generate decisions, but it does not yet have historical outcome evidence to judge their effectiveness.',

                facts: [
                    'total' => 0,

                    'pending' => 0,

                    'accepted' => 0,

                    'rejected' => 0,

                    'completed' => 0,

                    'financial_result' => 0.0,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'Money Imp has tracked %d recommendation outcomes: %d pending, %d accepted, %d rejected and %d completed. Completed recommendations have produced a recorded financial result of £%s.',
                $summary['total'],
                $summary['pending'],
                $summary['accepted'],
                $summary['rejected'],
                $summary['completed'],
                number_format(
                    $summary['financial_result'],
                    2
                )
            ),

            facts: $summary,

            evidence: [],

            confidence: 100,

            asOf: now()
        );
    }

    private function clientHistory(
        BusinessQuestion $question
    ): BusinessAnswer {
        $normalised =
            $question->normalised();

        $prefix =
            str_starts_with(
                $normalised,
                'what happened with '
            )
                ? 'what happened with '
                : 'what happened to ';

        $name =
            trim(
                substr(
                    $question->question,
                    strlen(
                        $prefix
                    )
                ),
                ' ?'
            );

        $client =
            Client::query()
                ->where(
                    'name',
                    'like',
                    '%'.$name.'%'
                )
                ->first();

        if (! $client) {
            return new BusinessAnswer(
                question: $question->question,

                answer: sprintf(
                    'I could not find a client matching "%s".',
                    $name
                ),

                facts: [
                    'client_found' => false,
                ],

                evidence: [],

                confidence: 0,

                asOf: now()
            );
        }

        $timeline =
            $this->timeline
                ->build(
                    $client
                );

        if ($timeline->events->isEmpty()) {
            return new BusinessAnswer(
                question: $question->question,

                answer: sprintf(
                    'Money Imp does not yet have any timeline events recorded for %s.',
                    $client->name
                ),

                facts: [
                    'client' => $client->name,

                    'event_count' => 0,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        $lines =
            $timeline
                ->events
                ->take(20)
                ->values()
                ->map(
                    fn ($event, int $index) => sprintf(
                        '%d. %s [%s] %s - %s',
                        $index + 1,
                        $event->occurredAt
                            ->format(
                                'Y-m-d'
                            ),
                        strtoupper(
                            $event->type
                        ),
                        $event->title,
                        $event->description
                    )
                );

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                '%s timeline:%s%s',
                $client->name,
                PHP_EOL.PHP_EOL,
                $lines->implode(
                    PHP_EOL
                )
            ),

            facts: [
                'client' => $client->name,

                'event_count' => $timeline
                    ->events
                    ->count(),

                'latest_event_type' => $timeline
                    ->events
                    ->first()
                    ->type,

                'latest_event_value' => $timeline
                    ->events
                    ->first()
                    ->value,
            ],

            evidence: $timeline
                ->events
                ->map(
                    fn ($event) => [
                        'type' => $event->type,

                        'title' => $event->title,

                        'description' => $event->description,

                        'value' => $event->value,

                        'importance' => $event->importance,

                        'occurred_at' => $event
                            ->occurredAt
                            ->toIso8601String(),

                        'metadata' => $event->metadata,
                    ]
                )
                ->values()
                ->all(),

            confidence: 100,

            asOf: now()
        );
    }

    private function executiveOpportunities(
        BusinessQuestion $question
    ): BusinessAnswer {
        $summary =
            $this->executiveReasoning
                ->current(
                    5
                );

        $top =
            $summary
                ->topOpportunities;

        $lines =
            $top
                ->map(
                    fn ($item, int $index) => sprintf(
                        '%d. %s - %s%s Score %d. Estimated effort %s.',
                        $index + 1,
                        $item->client ?? 'Business',
                        $item->recommendedAction,
                        $item->estimatedFinancialImpact !== null
                            ? sprintf(
                                ' Estimated financial impact £%s.',
                                number_format(
                                    $item->estimatedFinancialImpact,
                                    2
                                )
                            )
                            : ' ',
                        $item->score,
                        $item->estimatedEffortMinutes !== null
                            ? $item->estimatedEffortMinutes.' minutes'
                            : 'unknown'
                    )
                );

        $highest =
            $summary
                ->highestOpportunity;

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'CFO Imp has identified %d current executive opportunities with £%s of known financial impact. %d qualify as high-scoring quick wins representing £%s of known financial impact.%s%s',
                $summary->opportunityCount,
                number_format(
                    $summary->knownFinancialImpact,
                    2
                ),
                $summary->quickWinCount,
                number_format(
                    $summary->quickWinFinancialImpact,
                    2
                ),
                PHP_EOL.PHP_EOL,
                $lines->implode(
                    PHP_EOL
                )
            ),

            facts: [
                'opportunity_count' => $summary
                    ->opportunityCount,

                'known_financial_impact' => $summary
                    ->knownFinancialImpact,

                'quick_win_count' => $summary
                    ->quickWinCount,

                'quick_win_financial_impact' => $summary
                    ->quickWinFinancialImpact,

                'financial_opportunity_count' => $summary
                    ->financialOpportunityCount,

                'financial_control_count' => $summary
                    ->financialControlCount,

                'delivery_control_count' => $summary
                    ->deliveryControlCount,

                'operational_opportunity_count' => $summary
                    ->operationalOpportunityCount,

                'highest_opportunity_client' => $highest?->client,

                'highest_opportunity_score' => $highest?->score,
            ],

            evidence: $top
                ->map(
                    fn ($item) => [
                        'type' => $item->type,

                        'client_id' => $item->clientId,

                        'client' => $item->client,

                        'title' => $item->title,

                        'financial_impact' => $item
                            ->estimatedFinancialImpact,

                        'estimated_effort_minutes' => $item
                            ->estimatedEffortMinutes,

                        'confidence' => $item->confidence,

                        'urgency' => $item->urgency,

                        'score' => $item->score,

                        'recommended_action' => $item
                            ->recommendedAction,

                        'supporting_evidence' => $item
                            ->supportingEvidence,
                    ]
                )
                ->values()
                ->all(),

            confidence: $highest?->confidence
                ?? 0,

            asOf: now()
        );
    }

    private function learningSummary(
        BusinessQuestion $question
    ): BusinessAnswer {
        $profiles =
            $this->outcomeProfiles
                ->byType();

        if ($profiles->isEmpty()) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'CFO Imp has not yet observed enough completed executive actions to report meaningful outcome learning.',

                facts: [
                    'profile_count' => 0,

                    'usable_profile_count' => 0,
                ],

                evidence: [],

                confidence: 0,

                asOf: now()
            );
        }

        $evidence =
            $profiles
                ->map(
                    function ($profile) {
                        $learning =
                            $this->learningConfidence
                                ->forSample(
                                    $profile->completedCount
                                );

                        return [
                            'type' => $profile->type,

                            'completed_count' => $profile
                                ->completedCount,

                            'financial_success_count' => $profile
                                ->financialSuccessCount,

                            'financial_success_rate' => $profile
                                ->financialSuccessRate,

                            'total_financial_result' => $profile
                                ->totalFinancialResult,

                            'average_financial_result' => $profile
                                ->averageFinancialResult,

                            'average_completion_hours' => $profile
                                ->averageCompletionHours,

                            'learning_usable' => $learning
                                ->usable,

                            'learning_confidence' => $learning
                                ->confidence,
                        ];
                    }
                )
                ->values();

        $usable =
            $evidence
                ->where(
                    'learning_usable',
                    true
                );

        $best =
            $usable
                ->sortByDesc(
                    'financial_success_rate'
                )
                ->first();

        $answer =
            $usable->isEmpty()
                ? sprintf(
                    'CFO Imp has recorded outcome history across %d executive action type%s, but none has yet reached the minimum evidence threshold required to influence judgement.',
                    $profiles->count(),
                    $profiles->count() === 1
                        ? ''
                        : 's'
                )
                : sprintf(
                    'CFO Imp currently has usable historical learning for %d executive action type%s. The strongest observed strategy is %s with a %d%% financial success rate across %d completed actions and £%s of recorded financial result.',
                    $usable->count(),
                    $usable->count() === 1
                        ? ''
                        : 's',
                    str_replace(
                        '_',
                        ' ',
                        $best['type']
                    ),
                    $best['financial_success_rate'],
                    $best['completed_count'],
                    number_format(
                        $best['total_financial_result'],
                        2
                    )
                );

        return new BusinessAnswer(
            question: $question->question,

            answer: $answer,

            facts: [
                'profile_count' => $profiles
                    ->count(),

                'usable_profile_count' => $usable
                    ->count(),

                'best_learned_type' => $best['type']
                    ?? null,

                'best_success_rate' => $best[
                    'financial_success_rate'
                ] ?? null,
            ],

            evidence: $evidence
                ->all(),

            confidence: $usable->isEmpty()
                ? 0
                : (int) $usable
                    ->max(
                        'learning_confidence'
                    ),

            asOf: now()
        );
    }

    private function waitingActions(
        BusinessQuestion $question
    ): BusinessAnswer {
        $actions =
            $this->executiveActions
                ->byStatus(
                    'waiting'
                );

        if ($actions->isEmpty()) {
            return new BusinessAnswer(
                question: $question->question,

                answer: 'CFO Imp currently has no executive actions waiting on external dependencies.',

                facts: [
                    'waiting_action_count' => 0,

                    'waiting_financial_impact' => 0,
                ],

                evidence: [],

                confidence: 100,

                asOf: now()
            );
        }

        $financialImpact =
            (float) $actions
                ->sum(
                    fn ($action) => (float) (
                        $action->estimated_financial_impact
                        ?? 0
                    )
                );

        $top =
            $actions
                ->take(
                    10
                )
                ->values();

        $lines =
            $top
                ->map(
                    fn ($action, int $index) => sprintf(
                        '%d. %s - %s Waiting on: %s',
                        $index + 1,
                        $action->client ?? 'Business',
                        $action->title,
                        $action->outcome
                            ?? 'External dependency'
                    )
                );

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'CFO Imp has %d executive action%s waiting on external dependencies, representing £%s of known potential financial impact.%s%s',
                $actions->count(),
                $actions->count() === 1
                    ? ''
                    : 's',
                number_format(
                    $financialImpact,
                    2
                ),
                PHP_EOL.PHP_EOL,
                $lines->implode(
                    PHP_EOL
                )
            ),

            facts: [
                'waiting_action_count' => $actions
                    ->count(),

                'waiting_financial_impact' => $financialImpact,
            ],

            evidence: $top
                ->map(
                    fn ($action) => [
                        'id' => $action->id,

                        'client_id' => $action->client_id,

                        'client' => $action->client,

                        'type' => $action->type,

                        'title' => $action->title,

                        'waiting_reason' => $action->outcome,

                        'financial_impact' => $action
                            ->estimated_financial_impact !== null
                                ? (float) $action
                                    ->estimated_financial_impact
                                : null,

                        'score' => $action->score,

                        'started_at' => $action
                            ->started_at?->toIso8601String(),
                    ]
                )
                ->all(),

            confidence: 100,

            asOf: now()
        );
    }

    private function cashPosition(
        BusinessQuestion $question
    ): BusinessAnswer {
        $position =
            $this->financialPosition
                ->current();

        $cash =
            $position->cash;

        $credit =
            $position->credit;

        $liabilities =
            $position->liabilities;

        $receivables =
            $position->receivables;

        $safeCashStatement =
            $cash->safeAvailableCash !== null
                ? sprintf(
                    'Safe available cash is £%s.',
                    number_format(
                        $cash->safeAvailableCash,
                        2
                    )
                )
                : 'Safe available cash cannot yet be stated reliably because liability and/or banking coverage is incomplete.';

        return new BusinessAnswer(
            question: $question->question,

            answer: sprintf(
                'CFO Imp can currently verify £%s of bank cash and £%s of verified credit exposure. FreeAgent reports £%s across bank ledger accounts, but that is accounting-system evidence and is not verified current bank cash. A further £%s of historic card exposure is reported but unverified. Ledger receivables total £%s, but collectible value is not yet verified. Known liabilities total £%s with liability coverage at %d%%. %s',
                number_format(
                    $cash->verifiedCash,
                    2
                ),
                number_format(
                    $credit->verifiedExposure,
                    2
                ),
                number_format(
                    $cash->reportedAccountingBalance,
                    2
                ),
                number_format(
                    $cash->reportedUnverifiedCardDebt,
                    2
                ),
                number_format(
                    $receivables->ledgerOutstanding,
                    2
                ),
                number_format(
                    $liabilities->known,
                    2
                ),
                $liabilities->confidence,
                $safeCashStatement
            ),

            facts: [
                'verified_bank_cash' => $cash
                    ->verifiedCash,

                'reported_accounting_balance' => $cash
                    ->reportedAccountingBalance,

                'reported_unverified_card_debt' => $cash
                    ->reportedUnverifiedCardDebt,

                'verified_credit_exposure' => $credit
                    ->verifiedExposure,

                'reported_credit_exposure' => $credit
                    ->reportedExposure,

                'minimum_credit_payments_due' => $credit
                    ->minimumPaymentsDue,

                'ledger_receivables' => $receivables
                    ->ledgerOutstanding,

                'verified_collectible_receivables' => $receivables
                    ->verifiedCollectible,

                'known_liabilities' => $liabilities
                    ->known,

                'liability_coverage_complete' => $liabilities
                    ->coverageComplete,

                'safe_available_cash' => $cash
                    ->safeAvailableCash,

                'financial_position_confidence' => $position
                    ->confidence,
            ],

            evidence: $credit
                ->facilities
                ->map(
                    fn ($facility) => [
                        'type' => 'credit_facility',

                        'provider' => $facility
                            ->provider,

                        'name' => $facility
                            ->name,

                        'balance' => $facility
                            ->reportedBalance,

                        'minimum_payment' => $facility
                            ->minimumPayment,

                        'payment_due_at' => $facility
                            ->paymentDueAt,

                        'verified' => $facility
                            ->verified,

                        'confidence' => $facility
                            ->confidence,

                        'balance_at' => $facility
                            ->balanceAt,
                    ]
                )
                ->all(),

            confidence: $position
                ->confidence,

            asOf: now()
        );
    }
}
