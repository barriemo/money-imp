<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Conversation;

use App\Domains\BusinessBrain\Assertions\BusinessAssertionService;
use App\Domains\BusinessBrain\BankTruth\CanonicalPaymentEvidenceService;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Investigation\Cases\InvestigationCaseService;
use App\Domains\BusinessBrain\Investigation\Claims\HypothesisClaimAssessmentService;
use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\Investigation\HypothesisVerificationService;
use App\Domains\BusinessBrain\PaymentTruth\Conversation\Intent\ClientLedgerIntentResolver;
use App\Domains\BusinessBrain\PaymentTruth\Evidence\LedgerEvidenceJudge;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\BankSourceEvidenceCollector;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\ClientLedgerEvidenceCollector;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\PaymentHypothesisClaimBuilder;
use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerAnalysisService;
use App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence\ClientLedgerRiskService;
use App\Domains\BusinessBrain\Responses\BusinessResponse;
use App\Models\AccountingInvoice;
use App\Models\InvestigationCase;

class ClientLedgerConversationAction
{
    public function __construct(
        private ClientLedgerRiskService $risks,

        private ClientLedgerAnalysisService $ledger,

        private LedgerEvidenceJudge $judge,

        private BusinessAssertionService $assertions,

        private HypothesisVerificationService $verification,

        private PaymentHypothesisClaimBuilder $claimBuilder,

        private HypothesisClaimAssessmentService $claimAssessment,

        private InvestigationCaseService $cases,

        private ClientLedgerEvidenceCollector $ledgerEvidence,

        private BankSourceEvidenceCollector $bankSourceEvidence,

        private ClientLedgerIntentResolver $intents,

        private CanonicalPaymentEvidenceService $payments
    ) {}

    public function execute(
        string $question,
        ConversationContext $context
    ): BusinessResponse {
        if (! $context->subjectId) {
            return new BusinessResponse(
                answer: 'No client is currently selected.',
                context: $context
            );
        }

        if (
            $context->issue
            === 'awaiting_user_assertion'
        ) {
            $awaitingIntent =
                $this->intents->resolve(
                    $question
                );

            if (
                $awaitingIntent
                === 'begin_user_assertion'
            ) {
                return $this->beginAssertion(
                    $context
                );
            }

            return $this->recordAssertion(
                $question,
                $context
            );
        }

        $intent =
            $this->intents->resolve(
                $question
            );

        return match ($intent) {
            'show_invoices' => $this->showInvoices(
                $context
            ),

            'show_bank_receipts' => $this->showBankReceipts(
                $context
            ),

            'explain_anomaly' => $this->explain(
                $context
            ),

            'begin_user_assertion' => $this->beginAssertion(
                $context
            ),

            'verify_assertion' => $this->verifyAssertion(
                $context
            ),

            'show_missing_evidence' => $this->showMissingEvidence(
                $context
            ),

            'show_supporting_evidence' => $this->showInvestigationEvidence(
                $context,
                'supports'
            ),

            'show_contradicting_evidence' => $this->showInvestigationEvidence(
                $context,
                'contradicts'
            ),

            default => $this->summary(
                $context
            ),
        };
    }

    private function summary(
        ConversationContext $context
    ): BusinessResponse {
        $risk =
            $this->risk(
                $context->subjectId
            );

        if (! $risk) {
            return new BusinessResponse(
                answer: 'I could not find current ledger intelligence for that client.',
                context: $context
            );
        }

        return new BusinessResponse(
            answer: sprintf(
                '%s is currently classified as %s. Canonical cash received is %s, invoice evidence in the visible period is %s, leaving a ledger difference of %s. Confidence is %d%%.',
                $risk->clientName ?? $risk->clientId,
                str_replace(
                    '_',
                    ' ',
                    $risk->classification
                ),
                $this->money(
                    $risk->cashReceived
                ),
                $this->money(
                    $risk->invoiceValue
                ),
                $this->signedMoney(
                    $risk->difference
                ),
                $risk->confidence
            ),
            context: $context,
            questions: [
                'Show me the invoices.',
                'Show me the bank receipts.',
                'Why do you think this is wrong?',
                'I know what happened.',
            ],
            proposedActions: $risk->actions
        );
    }

    private function showInvoices(
        ConversationContext $context
    ): BusinessResponse {
        $invoices =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $context->subjectId
                )
                ->orderBy(
                    'invoice_date'
                )
                ->get();

        if ($invoices->isEmpty()) {
            return new BusinessResponse(
                answer: sprintf(
                    'I have no invoice evidence for %s.',
                    $context->subjectName
                        ?? 'this client'
                ),
                context: $context
            );
        }

        $lines =
            $invoices
                ->map(
                    fn ($invoice) => sprintf(
                        '%s | %s | gross %s | paid %s | outstanding %s | status %s',
                        $invoice->invoice_number,
                        $invoice->invoice_date?->toDateString()
                            ?? 'date unknown',
                        $this->money(
                            (float) $invoice->gross_amount
                        ),
                        $this->money(
                            (float) $invoice->paid_amount
                        ),
                        $this->money(
                            (float) $invoice->outstanding_amount
                        ),
                        $invoice->status
                    )
                )
                ->implode(
                    PHP_EOL
                );

        return new BusinessResponse(
            answer: sprintf(
                "I found %d invoices for %s:\n\n%s",
                $invoices->count(),
                $context->subjectName
                    ?? 'this client',
                $lines
            ),
            context: $context,
            questions: [
                'Show me the bank receipts.',
                'Which of these invoices are actually supported by bank evidence?',
            ]
        );
    }

    private function showBankReceipts(
        ConversationContext $context
    ): BusinessResponse {
        $payments =
            $this->payments
                ->customerPayments()
                ->where(
                    'clientId',
                    $context->subjectId
                )
                ->sortBy(
                    'date'
                )
                ->values();

        if ($payments->isEmpty()) {
            return new BusinessResponse(
                answer: sprintf(
                    'I have no canonical customer receipts for %s.',
                    $context->subjectName
                        ?? 'this client'
                ),
                context: $context
            );
        }

        $lines =
            $payments
                ->map(
                    fn ($payment) => sprintf(
                        '%s | %s | %s | evidence %d | confidence %d%%',
                        $payment->date,
                        $this->money(
                            $payment->amount
                        ),
                        $payment->description,
                        $payment->evidenceCount,
                        $payment->confidence
                    )
                )
                ->implode(
                    PHP_EOL
                );

        return new BusinessResponse(
            answer: sprintf(
                "I found %d canonical customer receipts for %s totalling %s:\n\n%s",
                $payments->count(),
                $context->subjectName
                    ?? 'this client',
                $this->money(
                    (float) $payments->sum(
                        'amount'
                    )
                ),
                $lines
            ),
            context: $context,
            questions: [
                'Show me the invoices.',
                'Why does the ledger not reconcile?',
            ]
        );
    }

    private function explain(
        ConversationContext $context
    ): BusinessResponse {
        $position =
            $this->ledger
                ->current()
                ->firstWhere(
                    'clientId',
                    $context->subjectId
                );

        if (! $position) {
            return new BusinessResponse(
                answer: 'I could not find current ledger evidence for that client.',
                context: $context
            );
        }

        $assessment =
            $this->judge
                ->assess(
                    $position
                );

        $observations =
            collect(
                $assessment->observations
            )
                ->map(
                    fn ($observation) => '- '.$observation
                )
                ->implode(
                    PHP_EOL
                );

        $causes =
            collect(
                $assessment->possibleCauses
            )
                ->map(
                    fn ($cause) => '- '.$cause
                )
                ->implode(
                    PHP_EOL
                );

        $answer =
            $this->assessmentOpening(
                $context,
                $assessment->status,
                $position->ledgerDifference
            );

        if ($observations !== '') {
            $answer .=
                "\n\nWhat I can see:\n"
                .$observations;
        }

        if ($causes !== '') {
            $answer .=
                "\n\nPossible explanations:\n"
                .$causes;
        }

        $answer .=
            "\n\nRecommendation:\n"
            .$assessment->recommendation
            ."\n\nConfidence in this evidence assessment: "
            .$assessment->confidence
            .'%';

        $context->hypothesis =
            $assessment->status;

        return new BusinessResponse(
            answer: $answer,
            context: $context,
            questions: [
                'Show me the invoices.',
                'Show me the bank receipts.',
                'I know what happened.',
            ]
        );
    }

    private function assessmentOpening(
        ConversationContext $context,
        string $status,
        float $difference
    ): string {
        $client =
            $context->subjectName
            ?? 'This client';

        return match ($status) {
            'incomplete_evidence' => sprintf(
                'I do not currently have enough evidence to conclude that %s has a confirmed ledger discrepancy of %s.',
                $client,
                $this->signedMoney(
                    $difference
                )
            ),

            'supported' => $client.' currently reconciles against the evidence I can see.',

            'contradictory_evidence' => sprintf(
                '%s has conflicting accounting and bank evidence around an apparent difference of %s.',
                $client,
                $this->signedMoney(
                    $difference
                )
            ),

            default => $client.' requires further evidence review.',
        };
    }

    private function investigationCase(
        ConversationContext $context
    ) {
        if ($context->investigationCaseId !== null) {
            $existing =
                InvestigationCase::query()
                    ->find(
                        $context->investigationCaseId
                    );

            if ($existing) {
                return $existing;
            }
        }

        $case =
            $this->cases
                ->findOrOpenForSubject(
                    type: 'client_ledger',

                    subjectType: $context->subjectType
                        ?? 'client',

                    subjectId: $context->subjectId,

                    subjectName: $context->subjectName,

                    title: sprintf(
                        'Why does %s not reconcile?',
                        $context->subjectName
                            ?? 'this client'
                    ),

                    question: 'Why does the client ledger not reconcile?'
                );

        $context->investigationCaseId =
            $case->id;

        return $case;
    }

    private function beginAssertion(
        ConversationContext $context
    ): BusinessResponse {
        $context->issue =
            'awaiting_user_assertion';

        return new BusinessResponse(
            answer: sprintf(
                'Okay. Tell me what happened with %s. I will record what you tell me as a user assertion, not as confirmed financial truth.',
                $context->subjectName
                    ?? 'this client'
            ),
            context: $context
        );
    }

    private function recordAssertion(
        string $statement,
        ConversationContext $context
    ): BusinessResponse {
        $assertion =
            $this->assertions
                ->record(
                    $statement,
                    $context
                );

        $case =
            $this->investigationCase(
                $context
            );

        $this->cases
            ->event(
                case: $case,

                type: 'hypothesis_asserted',

                description: $assertion->statement,

                actorType: 'user',

                payload: [
                    'status' => $assertion->status,
                    'source' => $assertion->source,
                ]
            );

        $case->forceFill([
            'current_hypothesis' => $assertion->statement,
            'status' => 'testing',
        ])->save();

        $context->issue =
            'client_ledger_assertion';

        return new BusinessResponse(
            answer: sprintf(
                'Understood. I have recorded this as a user assertion for %s: “%s” It is not confirmed financial truth yet. I can now test that assertion against the bank and accounting evidence before proposing any reconciliation.',
                $assertion->subjectName
                    ?? $assertion->subjectId,
                $assertion->statement
            ),
            context: $context,
            questions: [
                'Test that against the evidence.',
                'Show me what evidence supports it.',
                'Show me what evidence contradicts it.',
            ],
            proposedActions: [
                'Verify the user assertion against current financial evidence.',
            ]
        );
    }

    private function verifyAssertion(
        ConversationContext $context
    ): BusinessResponse {
        if (
            ! $context->hypothesis
            || ! $context->subjectId
        ) {
            return new BusinessResponse(
                answer: 'There is no current user assertion to test.',
                context: $context
            );
        }

        $hypothesis =
            new Hypothesis(
                statement: $context->hypothesis,

                subjectType: $context->subjectType
                    ?? 'client',

                subjectId: $context->subjectId,

                subjectName: $context->subjectName,

                assertedBy: 'user'
            );

        $result =
            $this->verification
                ->verify(
                    $hypothesis,
                    [
                        $this->ledgerEvidence,
                        $this->bankSourceEvidence,
                    ]
                );

        $claims =
            $this->claimAssessment
                ->assess(
                    $this->claimBuilder
                        ->build(
                            $hypothesis
                        ),
                    $result->evidence
                );

        $case =
            $this->investigationCase(
                $context
            );

        $this->cases
            ->assessmentEvent(
                case: $case,

                hypothesis: $hypothesis->statement,

                status: $result->status,

                confidence: $result->confidence,

                payload: [
                    'claims' => collect(
                        $claims->claims
                    )
                        ->map(
                            fn ($claim) => [
                                'key' => $claim->key,
                                'statement' => $claim->statement,
                                'status' => $claim->status,
                                'confidence' => $claim->confidence,
                            ]
                        )
                        ->values()
                        ->all(),

                    'missing_evidence' => $result->missingEvidence,
                ]
            );

        foreach ($claims->claims as $claim) {
            $this->cases
                ->claimAssessmentEvent(
                    case: $case,

                    key: $claim->key,

                    statement: $claim->statement,

                    status: $claim->status,

                    confidence: $claim->confidence,

                    evidence: $claim->evidence
                );
        }

        $case->forceFill([
            'current_hypothesis' => $hypothesis->statement,
            'confidence' => $result->confidence,
            'status' => $result->status === 'verified'
                ? 'verified'
                : 'waiting',
            'verdict' => $result->recommendation,
        ])->save();

        $evidence =
            collect(
                $result->evidence
            )
                ->map(
                    fn ($item) => sprintf(
                        '- [%s] %s: %s',
                        strtoupper(
                            $item->position
                        ),
                        $item->source,
                        $item->description
                    )
                )
                ->implode(
                    PHP_EOL
                );

        $missing =
            collect(
                $result->missingEvidence
            )
                ->map(
                    fn ($item) => '- '.$item
                )
                ->implode(
                    PHP_EOL
                );

        $answer =
            sprintf(
                "I tested your assertion:\n\n“%s”\n\nAssessment: %s\nConfidence: %d%%",
                $hypothesis->statement,
                strtoupper(
                    $result->status
                ),
                $result->confidence
            );

        $claimLines =
            collect(
                $claims->claims
            )
                ->map(
                    function ($claim): string {
                        $confidence =
                            $claim->confidence > 0
                                ? sprintf(
                                    ' — %d%%',
                                    $claim->confidence
                                )
                                : '';

                        return sprintf(
                            '- %s%s: %s',
                            strtoupper(
                                $claim->status
                            ),
                            $confidence,
                            $claim->statement
                        );
                    }
                )
                ->implode(
                    PHP_EOL
                );

        if ($claimLines !== '') {
            $answer .=
                "\n\nClaim assessment:\n"
                .$claimLines;
        }

        if ($evidence !== '') {
            $answer .=
                "\n\nEvidence:\n"
                .$evidence;
        }

        if ($missing !== '') {
            $answer .=
                "\n\nMissing evidence:\n"
                .$missing;
        }

        $answer .=
            "\n\nRecommendation:\n"
            .$result->recommendation;

        $context->issue =
            'client_ledger_investigation';

        $context->investigation = [
            'status' => $result->status,

            'confidence' => $result->confidence,

            'hypothesis' => $hypothesis->statement,

            'claims' => collect(
                $claims->claims
            )
                ->map(
                    fn ($claim) => [
                        'key' => $claim->key,
                        'statement' => $claim->statement,
                        'status' => $claim->status,
                        'confidence' => $claim->confidence,
                        'evidence' => $claim->evidence,
                    ]
                )
                ->values()
                ->all(),

            'evidence' => collect(
                $result->evidence
            )
                ->map(
                    fn ($item) => [
                        'source' => $item->source,
                        'description' => $item->description,
                        'position' => $item->position,
                        'confidence' => $item->confidence,
                        'metadata' => $item->metadata,
                    ]
                )
                ->values()
                ->all(),

            'missing_evidence' => $result->missingEvidence,

            'recommendation' => $result->recommendation,
        ];

        $context->pendingActions[] = [
            'type' => 'investigation_result',

            'subject_id' => $context->subjectId,

            'hypothesis' => $hypothesis->statement,

            'status' => $result->status,

            'confidence' => $result->confidence,
        ];

        return new BusinessResponse(
            answer: $answer,
            context: $context,
            questions: [
                'What evidence is still missing?',
                'Show me what supports my assertion.',
                'Show me what contradicts my assertion.',
            ]
        );
    }

    private function showMissingEvidence(
        ConversationContext $context
    ): BusinessResponse {
        $missing =
            collect(
                $context->investigation[
                    'missing_evidence'
                ] ?? []
            );

        if ($missing->isEmpty()) {
            return new BusinessResponse(
                answer: 'The last investigation did not identify any explicit missing evidence.',
                context: $context
            );
        }

        $lines =
            $missing
                ->values()
                ->map(
                    fn ($item, int $index) => sprintf(
                        '%d. %s',
                        $index + 1,
                        $item
                    )
                )
                ->implode(
                    PHP_EOL
                );

        return new BusinessResponse(
            answer: "The last investigation identified these evidence gaps:\n\n".$lines,
            context: $context,
            questions: [
                'Show me what supports my assertion.',
                'Show me what contradicts my assertion.',
            ]
        );
    }

    private function showInvestigationEvidence(
        ConversationContext $context,
        string $position
    ): BusinessResponse {
        $items =
            collect(
                $context->investigation[
                    'evidence'
                ] ?? []
            )
                ->where(
                    'position',
                    $position
                )
                ->values();

        if ($items->isEmpty()) {
            $message =
                $position === 'contradicts'
                    ? 'Nothing in the current evidence directly contradicts your assertion. That does not mean the assertion is proven; it means no contradictory evidence has been identified yet.'
                    : 'Nothing in the current evidence directly supports that part of your assertion.';

            return new BusinessResponse(
                answer: $message,
                context: $context
            );
        }

        $lines =
            $items
                ->map(
                    fn (array $item, int $index) => sprintf(
                        '%d. %s — %s (confidence %d%%)',
                        $index + 1,
                        $item['source'],
                        $item['description'],
                        $item['confidence']
                    )
                )
                ->implode(
                    PHP_EOL
                );

        $opening =
            $position === 'supports'
                ? 'The current evidence supporting your assertion is:'
                : 'The current evidence contradicting your assertion is:';

        return new BusinessResponse(
            answer: $opening."\n\n".$lines,
            context: $context,
            questions: [
                'What evidence is still missing?',
            ]
        );
    }

    private function risk(
        string $clientId
    ) {
        return $this->risks
            ->current()
            ->firstWhere(
                'clientId',
                $clientId
            );
    }

    private function money(
        float $value
    ): string {
        return '£'.number_format(
            abs($value),
            2
        );
    }

    private function signedMoney(
        float $value
    ): string {
        return ($value < 0 ? '-' : '')
            .$this->money(
                $value
            );
    }
}
