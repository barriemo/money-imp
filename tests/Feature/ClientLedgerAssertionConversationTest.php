<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\PaymentTruth\Conversation\ClientLedgerConversationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLedgerAssertionConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enter_assertion_mode_and_record_explanation(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                issue: 'client_ledger_anomaly'
            );

        $action =
            app(
                ClientLedgerConversationAction::class
            );

        $prompt =
            $action->execute(
                'I know what happened',
                $context
            );

        $this->assertSame(
            'awaiting_user_assertion',
            $prompt->context->issue
        );

        $response =
            $action->execute(
                'Those large invoices were paid into our old HSBC account.',
                $prompt->context
            );

        $this->assertSame(
            'client_ledger_assertion',
            $response->context->issue
        );

        $this->assertSame(
            'Those large invoices were paid into our old HSBC account.',
            $response->context->hypothesis
        );

        $this->assertSame(
            'pending_verification',
            $response->context->pendingActions[0]['status']
        );

        $this->assertStringContainsString(
            'not confirmed financial truth',
            $response->answer
        );
    }

    public function test_assertion_prompt_cannot_itself_become_the_hypothesis(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                issue: 'client_ledger_anomaly'
            );

        $action =
            app(
                ClientLedgerConversationAction::class
            );

        $first =
            $action->execute(
                'I know what happened',
                $context
            );

        $this->assertSame(
            'awaiting_user_assertion',
            $first->context->issue
        );

        $second =
            $action->execute(
                'I know what happened',
                $first->context
            );

        $this->assertSame(
            'awaiting_user_assertion',
            $second->context->issue
        );

        $this->assertNull(
            $second->context->hypothesis
        );

        $this->assertEmpty(
            $second->context->pendingActions
        );

        $third =
            $action->execute(
                'Those large invoices were paid into our old HSBC account.',
                $second->context
            );

        $this->assertSame(
            'client_ledger_assertion',
            $third->context->issue
        );

        $this->assertSame(
            'Those large invoices were paid into our old HSBC account.',
            $third->context->hypothesis
        );
    }
}
