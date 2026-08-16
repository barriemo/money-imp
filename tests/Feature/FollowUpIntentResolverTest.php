<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Conversation\Intent\FollowUpIntentResolver;
use Tests\TestCase;

class FollowUpIntentResolverTest extends TestCase
{
    public function test_confidence_question_resolves_to_explanation(): void
    {
        $intent =
            app(
                FollowUpIntentResolver::class
            )->resolve(
                'Why is confidence only 52%?'
            );

        $this->assertSame(
            'explain_confidence',
            $intent
        );
    }

    public function test_biggest_problem_question_resolves_to_problem_intent(): void
    {
        $intent =
            app(
                FollowUpIntentResolver::class
            )->resolve(
                'What is the biggest problem?'
            );

        $this->assertSame(
            'show_biggest_problem',
            $intent
        );
    }

    public function test_client_ledger_question_resolves_to_ledger_anomalies(): void
    {
        $intent =
            app(
                FollowUpIntentResolver::class
            )->resolve(
                'Show me the biggest client-ledger anomalies'
            );

        $this->assertSame(
            'show_ledger_anomalies',
            $intent
        );
    }
}
