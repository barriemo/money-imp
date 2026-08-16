<?php

namespace App\Domains\BusinessBrain\Assertions;

use App\Domains\BusinessBrain\Conversation\ConversationContext;

class BusinessAssertionService
{
    public function record(
        string $statement,
        ConversationContext $context
    ): BusinessAssertion {
        $assertion =
            new BusinessAssertion(
                subjectType: $context->subjectType
                    ?? 'unknown',

                subjectId: $context->subjectId
                    ?? 'unknown',

                subjectName: $context->subjectName,

                statement: trim(
                    $statement
                ),

                status: 'asserted',

                source: 'user',

                metadata: [
                    'issue' => $context->issue,
                ]
            );

        $context->hypothesis =
            $assertion->statement;

        $context->pendingActions[] = [
            'type' => 'verify_user_assertion',

            'subject_type' => $assertion->subjectType,

            'subject_id' => $assertion->subjectId,

            'statement' => $assertion->statement,

            'status' => 'pending_verification',
        ];

        return $assertion;
    }
}
