<?php

namespace App\Domains\BusinessBrain\Questions;

use App\Domains\BusinessBrain\Actions\GetPaymentTruthAction;
use App\Domains\BusinessBrain\Conversation\ConversationContext;

class BusinessQuestionRegistry
{
    public function resolve(
        string $question,
        ?ConversationContext $context = null
    ): ?string {
        $question =
            strtolower(
                trim(
                    $question
                )
            );

        if (
            str_contains($question, 'payment')
            || str_contains($question, 'receipt')
            || str_contains($question, 'paid us')
            || str_contains($question, 'customer money')
        ) {
            return GetPaymentTruthAction::class;
        }

        /*
         * Conversational continuation.
         *
         * If the question does not establish a new subject,
         * remain inside the capability currently being
         * discussed.
         */
        if (
            $context?->issue
            === 'customer_payment_truth'
        ) {
            return GetPaymentTruthAction::class;
        }

        return null;
    }
}
