<?php

namespace App\Domains\BusinessBrain\Query;

use App\Domains\BusinessBrain\Cfo\Conversation\CfoConversationAction;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Conversation\ConversationSubjectResolver;
use App\Domains\BusinessBrain\Experience\Conversation\ExperienceConversationAction;
use App\Domains\BusinessBrain\Investigation\Conversation\InvestigationConversationAction;
use App\Domains\BusinessBrain\PaymentTruth\Conversation\ClientLedgerConversationAction;
use App\Domains\BusinessBrain\PaymentTruth\Conversation\PaymentTruthConversationAction;
use App\Domains\BusinessBrain\Questions\BusinessQuestionRegistry;
use App\Domains\BusinessBrain\Responses\BusinessResponse;

class BusinessBrainQueryService
{
    public function __construct(
        private BusinessQuestionRegistry $registry,

        private PaymentTruthConversationAction $paymentConversation,

        private ConversationSubjectResolver $subjects,

        private ClientLedgerConversationAction $clientLedgerConversation,

        private InvestigationConversationAction $investigationConversation,

        private CfoConversationAction $cfoConversation,

        private ExperienceConversationAction $experienceConversation
    ) {}

    public function ask(
        string $question,
        ?ConversationContext $context = null
    ): ?BusinessResponse {
        $investigationContext =
            $context
            ?? new ConversationContext;

        $investigation =
            $this->investigationConversation
                ->execute(
                    $question,
                    $investigationContext
                );

        if ($investigation !== null) {
            return $investigation;
        }

        $experience =
            $this->experienceConversation
                ->execute(
                    $question,
                    $investigationContext
                );

        if ($experience !== null) {
            return $experience;
        }

        if (
            $context?->issue
            === 'client_ledger_anomalies'
        ) {
            $subject =
                $this->subjects->resolve(
                    $question,
                    $context
                );

            if ($subject !== null) {
                $context->subjectType =
                    'client';

                $context->subjectId =
                    $subject['client_id'];

                $context->subjectName =
                    $subject['client_name'];

                $context->issue =
                    'client_ledger_anomaly';

                $context->hypothesis =
                    $subject['classification']
                    ?? null;

                return $this->clientLedgerConversation
                    ->execute(
                        $question,
                        $context
                    );
            }

            return $this->paymentConversation
                ->execute(
                    $question,
                    $context
                );
        }

        if (
            in_array(
                $context?->issue,
                [
                    'client_ledger_anomaly',
                    'awaiting_user_assertion',
                    'client_ledger_assertion',
                    'client_ledger_investigation',
                    'investigation_history',
                ],
                true
            )
            && $context->subjectId !== null
        ) {
            return $this->clientLedgerConversation
                ->execute(
                    $question,
                    $context
                );
        }

        /*
         * Existing conversational subject gets first refusal.
         *
         * This allows:
         *
         * "How are customer payments looking?"
         * "Why only 52%?"
         * "What's the biggest problem?"
         *
         * to remain one conversation.
         */
        if (
            $context?->issue
            === 'customer_payment_truth'
        ) {
            return $this->paymentConversation
                ->execute(
                    $question,
                    $context
                );
        }

        $cfo =
            $this->cfoConversation
                ->execute(
                    $question,
                    $investigationContext
                );

        if ($cfo !== null) {
            return $cfo;
        }

        $action =
            $this->registry->resolve(
                $question,
                $context
            );

        if (! $action) {
            return null;
        }

        $insight =
            app($action)
                ->execute();

        $context ??=
            new ConversationContext;

        $context->issue =
            $context->issue
            ?? $this->issueFromInsight(
                $insight->headline
            );

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

    private function issueFromInsight(
        string $headline
    ): string {
        return strtolower(
            str_replace(
                ' ',
                '_',
                $headline
            )
        );
    }
}
