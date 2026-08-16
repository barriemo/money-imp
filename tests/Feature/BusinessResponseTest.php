<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\Responses\BusinessResponse;
use Tests\TestCase;

class BusinessResponseTest extends TestCase
{
    public function test_business_response_can_carry_context_questions_and_actions(): void
    {
        $context =
            new ConversationContext(
                subjectType: 'client',
                subjectId: 'client-123',
                subjectName: 'Peak Renewables'
            );

        $response =
            new BusinessResponse(
                answer: 'Peak Renewables has a high-confidence ledger anomaly.',
                context: $context,
                questions: [
                    'Do you want to inspect the invoices?',
                ],
                proposedActions: [
                    'Review unmatched customer receipts.',
                ]
            );

        $this->assertSame(
            'Peak Renewables',
            $response->context->subjectName
        );

        $this->assertCount(
            1,
            $response->questions
        );

        $this->assertCount(
            1,
            $response->proposedActions
        );
    }
}
