<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityActionExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_action_creates_executive_action(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Create referrals',
            'layers' => ['service'],
            'status' => 'ready',
        ]);

        $action = CapabilityAction::create([
            'capability_definition_id' => $capability->id,
            'name' => 'Identify happy clients',
        ]);

        $this->artisan(
            'imp:generate-actions'
        )->assertSuccessful();

        $executiveAction = ExecutiveAction::first();

        $this->assertNotNull(
            $executiveAction
        );

        $this->assertSame(
            $capability->id,
            $executiveAction->capability_definition_id
        );
    }
}
