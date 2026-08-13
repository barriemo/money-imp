<?php

namespace App\Domains\BusinessBrain\Interrogation;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Decisions\BusinessDecisionService;
use App\Domains\BusinessBrain\Interrogation\Attention\ClientAttentionService;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessCoverageSummaryService;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverageService;
use App\Domains\BusinessBrain\Interrogation\Position\BusinessPositionService;
use App\Domains\BusinessBrain\MorningBrief\Services\MorningBriefService;
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

        private BusinessDecisionService $decisions
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
        $decisions =
            $this->decisions
                ->today()
                ->take(5)
                ->values();

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
}
