<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\Services\ConversationalWorkLogService;
use App\Domains\WorkIntelligence\WorkObservation;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationalWorkLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_creates_unreviewed_work_log(): void
    {
        $client = Client::factory()->create();

        $user = User::factory()->create();

        $observations = new WorkObservationCollection(
            collect([
                new WorkObservation(
                    type: 'client_identified',
                    value: 'Walker',
                    confidence: 95
                ),

                new WorkObservation(
                    type: 'work_described',
                    value: 'Fixed Walker CRM integration',
                    confidence: 90
                ),

                new WorkObservation(
                    type: 'time_claimed',
                    value: 180,
                    confidence: 90
                ),
            ])
        );

        $log = app(
            ConversationalWorkLogService::class
        )->create(
            client: $client,
            user: $user,
            observations: $observations,
            minutes: 180,
            performedAt: now()
        );

        $this->assertDatabaseHas(
            'work_logs',
            [
                'id' => $log->id,
                'client_id' => $client->id,
                'minutes' => 180,
                'commercial_status' => 'unreviewed',
                'billing_hint' => 'billable',
            ]
        );

        $this->assertSame(
            'Fixed Walker CRM integration',
            $log->description
        );
    }
}
