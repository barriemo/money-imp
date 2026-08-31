<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Actions\ExecutiveActionAttentionService;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveActionAttentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_highest_priority_legitimate_actions(): void
    {
        $low = ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'attention-low'),
            'client_id' => null,
            'client' => null,
            'type' => 'cash_management',
            'title' => 'Lower priority opportunity',
            'description' => 'Lower priority.',
            'recommended_action' => 'Review.',
            'estimated_financial_impact' => 1000,
            'confidence' => 90,
            'urgency' => 60,
            'score' => 60,
            'status' => 'pending',
        ]);

        $high = ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'attention-high'),
            'client_id' => null,
            'client' => null,
            'type' => 'cash_management',
            'title' => 'Highest priority opportunity',
            'description' => 'Highest priority.',
            'recommended_action' => 'Chase client.',
            'estimated_financial_impact' => 12000,
            'confidence' => 100,
            'urgency' => 95,
            'score' => 98,
            'status' => 'pending',
        ]);

        ExecutiveAction::create([
            'fingerprint' => hash('sha256', 'attention-archived'),
            'client_id' => null,
            'client' => null,
            'type' => 'cash_management',
            'title' => 'Archived opportunity',
            'description' => 'Archived.',
            'recommended_action' => 'Ignore.',
            'estimated_financial_impact' => 50000,
            'confidence' => 100,
            'urgency' => 100,
            'score' => 100,
            'status' => 'archived',
        ]);

        $attention = app(
            ExecutiveActionAttentionService::class
        )->current();

        $this->assertSame(
            $high->id,
            $attention->first()->id
        );

        $this->assertTrue(
            $attention->contains(
                fn (ExecutiveAction $action) => $action->id === $low->id
            )
        );

        $this->assertFalse(
            $attention->contains(
                fn (ExecutiveAction $action) => $action->status === 'archived'
            )
        );
    }

    public function test_current_can_limit_attention_items(): void
    {
        foreach (range(1, 6) as $index) {
            ExecutiveAction::create([
                'fingerprint' => hash(
                    'sha256',
                    'attention-limit-'.$index
                ),
                'client_id' => null,
                'client' => null,
                'type' => 'cash_management',
                'title' => 'Opportunity '.$index,
                'description' => 'Opportunity.',
                'recommended_action' => 'Review.',
                'estimated_financial_impact' => $index * 1000,
                'confidence' => 100,
                'urgency' => 50 + $index,
                'score' => 50 + $index,
                'status' => 'pending',
            ]);
        }

        $attention = app(
            ExecutiveActionAttentionService::class
        )->current(3);

        $this->assertCount(3, $attention);
        $this->assertSame(56, $attention->first()->score);
    }
}
