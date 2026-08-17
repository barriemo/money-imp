<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use App\Models\ExecutiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityActionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_same_capability_action_twice_does_not_duplicate(): void
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

        $this->artisan(
            'imp:generate-actions'
        )->assertSuccessful();

        $this->assertCount(
            1,
            ExecutiveAction::all()
        );
    }
}
