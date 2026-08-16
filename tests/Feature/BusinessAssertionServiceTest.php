<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Assertions\BusinessAssertionService;
use App\Domains\BusinessBrain\Conversation\ConversationContext;
use Tests\TestCase;

class BusinessAssertionServiceTest extends TestCase
{
    public function test_user_statement_is_recorded_as_assertion_not_confirmed_truth(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'peak',
                subjectName: 'PEAK RENEWABLES (SCOTLAND) LTD',
                issue: 'client_ledger_anomaly'
            );

        $assertion =
            app(
                BusinessAssertionService::class
            )->record(
                'Those large invoices were paid into our old HSBC account.',
                $context
            );

        $this->assertSame(
            'asserted',
            $assertion->status
        );

        $this->assertSame(
            'user',
            $assertion->source
        );

        $this->assertSame(
            'Those large invoices were paid into our old HSBC account.',
            $context->hypothesis
        );

        $this->assertSame(
            'pending_verification',
            $context->pendingActions[0]['status']
        );
    }
}
