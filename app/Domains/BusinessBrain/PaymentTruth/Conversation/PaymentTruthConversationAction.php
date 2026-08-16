<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Conversation;

use App\Domains\BusinessBrain\Actions\GetPaymentTruthAction;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Conversation\Intent\FollowUpIntentResolver;
use App\Domains\BusinessBrain\PaymentTruth\Analysis\UnmatchedPaymentAnalysisService;
use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRiskService;
use App\Domains\BusinessBrain\PaymentTruth\Position\PaymentTruthPositionService;
use App\Domains\BusinessBrain\Responses\BusinessResponse;

class PaymentTruthConversationAction
{
    public function __construct(
        private GetPaymentTruthAction $summary,
        private PaymentTruthPositionService $position,
        private UnmatchedPaymentAnalysisService $unmatched,
        private ClientLedgerRiskService $ledgerRisks,

        private FollowUpIntentResolver $intents
    ) {}

    public function execute(
        string $question,
        ConversationContext $context
    ): BusinessResponse {
        $intent =
            $this->intents->resolve(
                $question
            );

        return match ($intent) {
            'explain_confidence' => $this->explainConfidence(
                $context
            ),

            'show_biggest_problem' => $this->biggestProblem(
                $context
            ),

            'show_ledger_anomalies' => $this->ledgerAnomalies(
                $context
            ),

            default => $this->summary(
                $context
            ),
        };
    }

    private function summary(
        ConversationContext $context
    ): BusinessResponse {
        $insight =
            $this->summary
                ->execute();

        return new BusinessResponse(
            answer: $insight->summary,
            insight: $insight,
            context: $context,
            questions: [
                'Why is confidence at this level?',
                'What is the biggest unresolved payment problem?',
            ],
            proposedActions: $insight->actions
        );
    }

    private function explainConfidence(
        ConversationContext $context
    ): BusinessResponse {
        $position =
            $this->position
                ->current();

        $answer =
            sprintf(
                'Confidence is %d%% because none of the %d canonical customer payments are confirmed against invoices yet. %d payments worth %s have suggested matches, while %d payments worth %s remain unmatched. Confirmed payments are weighted highest, suggested matches lower, and unmatched payments lowest.',
                $position->confidence,
                $position->paymentCount,
                $position->suggestedPaymentCount,
                $this->money(
                    $position->suggestedReceived
                ),
                $position->unmatchedPaymentCount,
                $this->money(
                    $position->unmatchedReceived
                )
            );

        return new BusinessResponse(
            answer: $answer,
            context: $context,
            questions: [
                'What is the biggest unresolved payment problem?',
                'Show me the supporting evidence.',
            ]
        );
    }

    private function biggestProblem(
        ConversationContext $context
    ): BusinessResponse {
        $analysis =
            $this->unmatched
                ->current();

        $answer =
            sprintf(
                'The largest unresolved payment problem is the no-exact-match group: %d payments worth %s. A further %d payments worth %s have ambiguous exact invoice matches. That means the bigger problem is not simple invoice matching; it is client-ledger and multi-invoice reconciliation.',
                $analysis->noExactMatchCount,
                $this->money(
                    $analysis->noExactMatchValue
                ),
                $analysis->ambiguousExactMatchCount,
                $this->money(
                    $analysis->ambiguousExactMatchValue
                )
            );

        return new BusinessResponse(
            answer: $answer,
            context: $context,
            questions: [
                'Show me the biggest client-ledger anomalies.',
                'Work through the no-exact-match group.',
            ],
            proposedActions: [
                'Analyse unmatched payments by client ledger.',
            ]
        );
    }

    private function ledgerAnomalies(
        ConversationContext $context
    ): BusinessResponse {
        $risks =
            $this->ledgerRisks
                ->current()
                ->filter(
                    fn ($risk) => $risk->classification
                        !== 'ledger_reconciled'
                )
                ->take(5)
                ->values();

        $lines =
            $risks
                ->map(
                    function ($risk, int $index): string {
                        return sprintf(
                            '%d. %s — %s, difference %s, priority %d, confidence %d%%.',
                            $index + 1,
                            $risk->clientName ?? $risk->clientId,
                            str_replace(
                                '_',
                                ' ',
                                $risk->classification
                            ),
                            $this->signedMoney(
                                $risk->difference
                            ),
                            $risk->priority,
                            $risk->confidence
                        );
                    }
                )
                ->implode(
                    PHP_EOL
                );

        $context->issue =
            'client_ledger_anomalies';

        $context->unresolvedQuestions =
            $risks
                ->map(
                    fn ($risk) => [
                        'client_id' => $risk->clientId,
                        'client_name' => $risk->clientName,
                        'classification' => $risk->classification,
                        'difference' => $risk->difference,
                        'priority' => $risk->priority,
                    ]
                )
                ->all();

        return new BusinessResponse(
            answer: "The highest-priority client-ledger issues are:\n\n".$lines,
            context: $context,
            questions: [
                'Which one do you want to investigate?',
                'You can say, for example, “let’s do Peak”.',
            ]
        );
    }

    private function signedMoney(
        float $value
    ): string {
        return ($value < 0 ? '-' : '')
            .'£'.number_format(
                abs($value),
                2
            );
    }

    private function money(
        float $value
    ): string {
        return '£'.number_format(
            $value,
            2
        );
    }
}
