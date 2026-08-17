<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityBusinessActionsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_generated_actions_are_visible_in_business_actions(): void
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
            'business:actions'
        )
            ->expectsOutputToContain(
                'Identify happy clients'
            )
            ->assertSuccessful();
    }
}
