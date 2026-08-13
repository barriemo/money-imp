<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Memory\BusinessMemoryEventService;
use App\Models\BusinessDecisionOutcome;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMemoryEventServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_decision_becomes_business_memory(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Memory Client',
            ]);

        $outcome =
            BusinessDecisionOutcome::create([
                'fingerprint' => hash(
                    'sha256',
                    'memory-test'
                ),

                'decision_type' => 'collections',

                'client_id' => $client->id,

                'client' => $client->name,

                'action' => 'Chase overdue balance.',

                'reason' => '£5,000 was overdue.',

                'priority' => 90,

                'value' => 5000,

                'status' => 'completed',

                'outcome' => 'Client paid outstanding balance.',

                'financial_result' => 5000,

                'completed_at' => now(),
            ]);

        $event =
            app(
                BusinessMemoryEventService::class
            )->recordDecisionOutcome(
                $outcome
            );

        $this->assertSame(
            'decision_outcome',
            $event->type
        );

        $this->assertSame(
            'Memory Client',
            $event->client
        );

        $this->assertSame(
            5000.0,
            $event->value
        );

        $this->assertSame(
            'Client paid outstanding balance.',
            $event->description
        );
    }
}
