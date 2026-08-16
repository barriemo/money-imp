<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\ConversationContext;
use App\Domains\BusinessBrain\PaymentTruth\Conversation\PaymentTruthConversationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTruthConversationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_confidence_follow_up_returns_explanation_instead_of_summary(): void
    {
        $context =
            new ConversationContext(
                issue: 'customer_payment_truth'
            );

        $response =
            app(
                PaymentTruthConversationAction::class
            )->execute(
                'Why is confidence only 52%?',
                $context
            );

        $this->assertStringContainsString(
            'Confidence is',
            $response->answer
        );

        $this->assertStringContainsString(
            'confirmed against invoices',
            $response->answer
        );

        $this->assertSame(
            'customer_payment_truth',
            $response->context->issue
        );
    }

    public function test_biggest_problem_follow_up_explains_unmatched_population(): void
    {
        $context =
            new ConversationContext(
                issue: 'customer_payment_truth'
            );

        $response =
            app(
                PaymentTruthConversationAction::class
            )->execute(
                'What is the biggest problem?',
                $context
            );

        $this->assertStringContainsString(
            'largest unresolved payment problem',
            $response->answer
        );
    }
}
