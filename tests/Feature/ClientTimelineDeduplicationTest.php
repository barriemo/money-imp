<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Timeline\ClientTimelineBuilder;
use App\Models\BusinessDecisionOutcome;
use App\Models\BusinessMemoryEvent;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTimelineDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_memory_derived_from_decision_does_not_duplicate_timeline_event(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Timeline Client',
            ]);

        $outcome =
            BusinessDecisionOutcome::create([
                'fingerprint' => hash(
                    'sha256',
                    'timeline-dedupe'
                ),

                'decision_type' => 'collections',

                'client_id' => $client->id,

                'client' => $client->name,

                'action' => 'Chase overdue balance.',

                'reason' => '£5,000 is overdue.',

                'priority' => 90,

                'value' => 5000,

                'status' => 'completed',

                'outcome' => 'Client paid.',

                'financial_result' => 5000,

                'decided_at' => now(),

                'completed_at' => now(),
            ]);

        BusinessMemoryEvent::create([
            'client_id' => $client->id,

            'client' => $client->name,

            'type' => 'decision_outcome',

            'source_type' => 'business_decision_outcome',

            'source_id' => $outcome->id,

            'title' => 'Collections recommendation completed',

            'description' => 'Client paid.',

            'value' => 5000,

            'confidence' => 100,

            'occurred_at' => now(),
        ]);

        $timeline =
            app(
                ClientTimelineBuilder::class
            )->build(
                $client
            );

        $this->assertCount(
            1,
            $timeline->events
        );

        $this->assertSame(
            'decision',
            $timeline->events
                ->first()
                ->type
        );
    }
}
