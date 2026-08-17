<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityCatalogueActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_catalogue_can_display_actions(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Turn happy clients into introductions',
            'layers' => [
                'model',
            ],
            'status' => 'ready',
        ]);

        CapabilityAction::create([
            'capability_definition_id' => $capability->id,
            'name' => 'Identify happy clients',
        ]);

        $this->artisan(
            'imp:capabilities'
        )
            ->expectsOutputToContain(
                'Actions:'
            )
            ->expectsOutputToContain(
                '- Identify happy clients'
            )
            ->assertSuccessful();
    }
}
