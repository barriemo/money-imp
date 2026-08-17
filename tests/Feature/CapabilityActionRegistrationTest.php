<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityActionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_action_command_creates_action_for_capability(): void
    {
        $capability = CapabilityDefinition::create([
            'name' => 'ClientAdvocacy',
            'domain' => 'BusinessBrain',
            'area' => 'Client',
            'owner' => 'ReferralImp',
            'purpose' => 'Turn happy clients into introductions',
            'layers' => [
                'model',
                'service',
            ],
            'status' => 'ready',
        ]);

        $this->artisan(
            'imp:register-action ClientAdvocacy "Identify happy clients"'
        )
            ->expectsOutputToContain(
                'Registered action: Identify happy clients'
            )
            ->assertSuccessful();

        $this->assertDatabaseHas(
            'capability_actions',
            [
                'capability_definition_id' => $capability->id,
                'name' => 'Identify happy clients',
            ]
        );

        $this->assertInstanceOf(
            CapabilityAction::class,
            $capability->fresh()->actions->first()
        );
    }
}
