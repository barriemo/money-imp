<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Reasoning\ExecutiveAttentionPolicy;
use App\Domains\BusinessBrain\Reasoning\ExecutiveReasoning;
use Tests\TestCase;

class ExecutiveAttentionFilteringTest extends TestCase
{
    public function test_repetitive_payment_control_does_not_surface(): void
    {
        $reasoning = $this->reasoning(
            type: 'financial_control',
            score: 74,
            urgency: 80,
            financialImpact: null,
        );

        $this->assertFalse(
            app(ExecutiveAttentionPolicy::class)
                ->shouldSurface($reasoning)
        );
    }

    public function test_material_control_exception_can_surface(): void
    {
        $reasoning = $this->reasoning(
            type: 'financial_control',
            score: 74,
            urgency: 80,
            financialImpact: 15000,
        );

        $this->assertTrue(
            app(ExecutiveAttentionPolicy::class)
                ->shouldSurface($reasoning)
        );
    }

    public function test_critical_reasoning_surfaces_regardless_of_type(): void
    {
        $reasoning = $this->reasoning(
            type: 'delivery_control',
            score: 90,
            urgency: 90,
            financialImpact: null,
        );

        $this->assertTrue(
            app(ExecutiveAttentionPolicy::class)
                ->shouldSurface($reasoning)
        );
    }

    public function test_financial_opportunity_surfaces(): void
    {
        $reasoning = $this->reasoning(
            type: 'financial_opportunity',
            score: 70,
            urgency: 70,
            financialImpact: 5000,
        );

        $this->assertTrue(
            app(ExecutiveAttentionPolicy::class)
                ->shouldSurface($reasoning)
        );
    }

    private function reasoning(
        string $type,
        int $score,
        int $urgency,
        ?float $financialImpact,
    ): ExecutiveReasoning {
        return new ExecutiveReasoning(
            type: $type,
            clientId: 'client-1',
            client: 'Client One',
            title: 'Test reasoning',
            description: 'Test reasoning description.',
            estimatedFinancialImpact: $financialImpact,
            estimatedEffortMinutes: 30,
            confidence: 100,
            urgency: $urgency,
            score: $score,
            recommendedAction: 'Review this',
            supportingEvidence: [],
        );
    }
}
