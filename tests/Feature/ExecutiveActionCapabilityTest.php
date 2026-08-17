<?php

namespace Tests\Feature;

use App\Models\CapabilityDefinition;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveActionCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_action_can_belong_to_capability(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Turn happy clients into introductions',
            'layers' => [
                'service',
            ],
            'status' => 'ready',
        ]);

        $action = ExecutiveAction::create([
            'fingerprint' => 'client-advocacy-test-action',
            'type' => 'client_advocacy',
            'title' => 'Ask happy client for introduction',
            'description' => 'A happy client should be approached for a referral.',
            'recommended_action' => 'Contact client and request introduction.',
            'confidence' => 90,
            'urgency' => 80,
            'score' => 85,
            'status' => 'pending',
            'capability_definition_id' => $capability->id,
        ]);

        $this->assertSame(
            $capability->id,
            $action->fresh()->capability->id
        );

        $this->assertCount(
            1,
            $capability->fresh()->executiveActions
        );

        $this->assertCount(
            1,
            $capability->executiveActions
        );
    }
}
