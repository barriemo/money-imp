<?php

namespace Tests\Feature;

use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Knowledge\ManagedServiceComponentKnowledgeService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagedServiceComponentKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_can_be_known_without_fake_asset(): void
    {
        $client =
            Client::factory()->create();

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 185
        );

        $knowledge = app(
            ManagedServiceComponentKnowledgeService::class
        )->remember(
            service: $service,
            componentType: 'backup',
            value: 'Backups are managed by Dave at XYZ IT.',
            state: 'externally_managed',
            confidence: 100,
            verified: false,
            source: 'charlie_answer'
        );

        $this->assertSame(
            'backup',
            $knowledge->component_type
        );

        $this->assertSame(
            'externally_managed',
            $knowledge->state
        );

        $this->assertSame(
            'Backups are managed by Dave at XYZ IT.',
            $knowledge->value
        );

        $this->assertFalse(
            $knowledge->verified
        );

        $this->assertDatabaseCount(
            'supplier_assets',
            0
        );
    }
}
