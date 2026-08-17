<?php

namespace Tests\Feature;

use App\Models\CapabilityAction;
use App\Models\CapabilityDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_capability_can_have_actions(): void
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

        $action = CapabilityAction::create([
            'capability_definition_id' => $capability->id,
            'name' => 'Identify happy clients',
            'description' => 'Find clients suitable for advocacy requests',
            'priority' => 90,
        ]);

        $this->assertCount(
            1,
            $capability->actions
        );

        $this->assertSame(
            'Identify happy clients',
            $action->name
        );
    }
}
