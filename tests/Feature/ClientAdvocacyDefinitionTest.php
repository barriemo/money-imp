<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Capabilities\Definitions\ClientAdvocacyDefinition;
use Tests\TestCase;

class ClientAdvocacyDefinitionTest extends TestCase
{
    public function test_client_advocacy_definition_contains_capability_metadata(): void
    {
        $definition = new ClientAdvocacyDefinition;

        $capability = $definition->definition();

        $this->assertSame(
            'ClientAdvocacy',
            $capability['name']
        );

        $this->assertSame(
            'BusinessBrain',
            $capability['domain']
        );

        $this->assertSame(
            'ReferralImp',
            $capability['owner']
        );
    }

    public function test_client_advocacy_definition_contains_actions(): void
    {
        $definition = new ClientAdvocacyDefinition;

        $actions = $definition->actions();

        $this->assertContains(
            'Identify happy clients',
            $actions
        );

        $this->assertContains(
            'Request introductions',
            $actions
        );
    }
}
