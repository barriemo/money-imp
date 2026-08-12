<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\Services\ConversationalWorkLogService;
use App\Domains\WorkIntelligence\WorkObservation;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationalWorkLogManyTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_creates_multiple_work_logs(): void
    {
        $client =
            Client::factory()->create();

        $user =
            User::factory()->create();

        $observations =
            new WorkObservationCollection(
                collect([
                    new WorkObservation(
                        type: 'client_identified',
                        value: 'Walker',
                        confidence: 95
                    ),

                    new WorkObservation(
                        type: 'work_described',
                        value: 'Fixed Walker CRM integration',
                        confidence: 90,
                        metadata: [
                            'minutes' => 120,
                        ]
                    ),

                    new WorkObservation(
                        type: 'work_described',
                        value: 'Configured analytics tracking',
                        confidence: 85,
                        metadata: [
                            'minutes' => 60,
                        ]
                    ),

                    new WorkObservation(
                        type: 'time_claimed',
                        value: 180,
                        confidence: 90
                    ),
                ])
            );

        $logs =
            app(
                ConversationalWorkLogService::class
            )
                ->createMany(
                    $client,
                    $user,
                    $observations,
                    now()
                );

        $this->assertCount(
            2,
            $logs
        );

        $this->assertDatabaseCount(
            'work_logs',
            2
        );
    }
}
