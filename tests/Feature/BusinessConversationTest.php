<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\BusinessConversation;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Conversation\ConversationTurn;
use Tests\TestCase;

class BusinessConversationTest extends TestCase
{
    public function test_conversation_retains_subject_context_between_turns(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'client-123',
                subjectName: 'Walker The Jeweller',
                issue: 'ledger_difference'
            );

        $conversation =
            new BusinessConversation(
                context: $context
            );

        $conversation->add(
            new ConversationTurn(
                role: 'user',
                message: 'What about Walker?'
            )
        );

        $conversation->add(
            new ConversationTurn(
                role: 'assistant',
                message: 'Walker has a material ledger difference.'
            )
        );

        $this->assertSame(
            'Walker The Jeweller',
            $conversation->context->subjectName
        );

        $this->assertSame(
            'ledger_difference',
            $conversation->context->issue
        );

        $this->assertSame(
            'Walker has a material ledger difference.',
            $conversation->lastTurn()->message
        );
    }
}
