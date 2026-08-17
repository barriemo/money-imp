<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityExecutiveQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_generated_actions_appear_in_executive_queue(): void
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

        CapabilityAction::create([
            'capability_definition_id' => $capability->id,
            'name' => 'Identify happy clients',
        ]);

        $this->artisan(
            'imp:generate-actions'
        )->assertSuccessful();

        $this->assertCount(
            1,
            ExecutiveAction::all()
        );

        $this->assertSame(
            'ClientAdvocacy',
            ExecutiveAction::first()->type
        );

        $this->assertSame(
            $capability->id,
            ExecutiveAction::first()->capability_definition_id
        );
    }
}
