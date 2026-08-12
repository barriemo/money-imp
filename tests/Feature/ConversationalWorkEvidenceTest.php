<?php

namespace Tests\Feature;

use App\Domains\Evidence\EvidenceRepository;
use App\Domains\WorkIntelligence\Services\ConversationalWorkLogService;
use App\Domains\WorkIntelligence\WorkObservation;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationalWorkEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversational_work_log_emits_evidence(): void
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
                        value: $client->name,
                        confidence: 95
                    ),

                    new WorkObservation(
                        type: 'work_described',
                        value: 'Fixed CRM integration',
                        confidence: 90
                    ),

                    new WorkObservation(
                        type: 'time_claimed',
                        value: 2,
                        confidence: 100
                    ),
                ])
            );

        app(
            ConversationalWorkLogService::class
        )->create(
            client: $client,
            user: $user,
            observations: $observations,
            minutes: 120,
            performedAt: now()
        );

        $evidence =
            app(
                EvidenceRepository::class
            )
                ->all();

        $this->assertCount(
            1,
            $evidence
        );

        $this->assertSame(
            'work_log',
            $evidence->first()->type
        );

        $this->assertSame(
            $client->id,
            $evidence->first()->metadata['client_id']
        );
    }
}
