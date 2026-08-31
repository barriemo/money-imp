<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\ExecutiveActionPromotionPolicy;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use Tests\TestCase;

class ExecutiveActionPromotionPolicyTest extends TestCase
{
    public function test_client_specific_financial_opportunity_is_promoted(): void
    {
        $reasoning = $this->reasoning(
            type: 'financial_opportunity',
            clientId: 'client-1',
        );

        $this->assertTrue(
            app(ExecutiveActionPromotionPolicy::class)
                ->shouldPromote($reasoning)
        );
    }

    public function test_aggregate_financial_opportunity_is_not_promoted(): void
    {
        $reasoning = $this->reasoning(
            type: 'financial_opportunity',
            clientId: null,
        );

        $this->assertFalse(
            app(ExecutiveActionPromotionPolicy::class)
                ->shouldPromote($reasoning)
        );
    }

    public function test_aggregate_receivable_recovery_is_not_promoted(): void
    {
        $reasoning = $this->reasoning(
            type: 'receivable_recovery',
            clientId: null,
        );

        $this->assertFalse(
            app(ExecutiveActionPromotionPolicy::class)
                ->shouldPromote($reasoning)
        );
    }

    public function test_cash_management_is_promoted(): void
    {
        $reasoning = $this->reasoning(
            type: 'cash_management',
            clientId: null,
        );

        $this->assertTrue(
            app(ExecutiveActionPromotionPolicy::class)
                ->shouldPromote($reasoning)
        );
    }

    public function test_client_advocacy_is_promoted(): void
    {
        $reasoning = $this->reasoning(
            type: 'client_advocacy',
            clientId: 'client-1',
        );

        $this->assertTrue(
            app(ExecutiveActionPromotionPolicy::class)
                ->shouldPromote($reasoning)
        );
    }

    private function reasoning(
        string $type,
        ?string $clientId,
    ): ExecutiveReasoning {
        return new ExecutiveReasoning(
            type: $type,
            clientId: $clientId,
            client: $clientId ? 'Client One' : null,
            title: 'Test reasoning',
            description: 'Test reasoning description.',
            estimatedFinancialImpact: 10000,
            estimatedEffortMinutes: 30,
            confidence: 100,
            urgency: 90,
            score: 90,
            recommendedAction: 'Review this',
            supportingEvidence: [],
        );
    }
}
