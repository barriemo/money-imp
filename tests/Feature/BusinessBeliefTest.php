<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefService;
use App\Models\BusinessContext;
use App\Models\BusinessMemory;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBeliefTest extends TestCase
{
    use RefreshDatabase;

    public function test_belief_is_supported_by_evidence(): void
    {
        $client =
            Client::factory()->create();

        $belief = app(
            BusinessBeliefService::class
        )->remember(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Dave at XYZ IT',
            source: 'charlie'
        );

        $context =
            BusinessContext::create([
                'business_memory_id' => BusinessMemory::create([
                    'subject_type' => $client->getMorphClass(),

                    'subject_id' => $client->id,

                    'title' => $client->name,

                    'status' => 'active',
                ])->id,

                'context_type' => 'current_supplier',

                'key' => 'backup_provider',

                'value' => 'Dave at XYZ IT',

                'confidence' => 100,

                'verified' => true,

                'source' => 'charlie_answer',
            ]);

        app(
            BusinessBeliefService::class
        )->addEvidence(
            belief: $belief,
            evidence: $context,
            relationship: 'supports',
            weight: 90,
            confidence: 100,
            summary: 'Owner confirmed backup provider.'
        );

        $belief->refresh();

        $this->assertSame(
            100,
            $belief->confidence
        );

        $this->assertSame(
            1,
            $belief
                ->evidence()
                ->count()
        );
    }
}
