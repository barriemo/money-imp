<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefContradictionService;
use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefService;
use App\Models\BusinessMemory;
use App\Models\BusinessMemoryEntry;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBeliefContradictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_contradicting_evidence_reduces_belief_confidence(): void
    {
        $client =
            Client::factory()->create();

        $belief = app(
            BusinessBeliefService::class
        )->remember(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Dave at XYZ IT'
        );

        $memory =
            BusinessMemory::create([
                'subject_type' => $client->getMorphClass(),

                'subject_id' => $client->id,

                'title' => $client->name,

                'status' => 'active',
            ]);

        $support =
            BusinessMemoryEntry::create([
                'business_memory_id' => $memory->id,

                'entry_type' => 'note',

                'occurred_at' => now(),

                'content' => 'Dave looks after backups.',

                'source' => 'owner_context',

                'confidence' => 100,

                'verified' => true,
            ]);

        $contradiction =
            BusinessMemoryEntry::create([
                'business_memory_id' => $memory->id,

                'entry_type' => 'document',

                'occurred_at' => now(),

                'content' => 'Acronis Backup invoice received.',

                'source' => 'supplier_invoice',

                'confidence' => 100,

                'verified' => true,
            ]);

        $service = app(
            BusinessBeliefService::class
        );

        $service->addEvidence(
            belief: $belief,
            evidence: $support,
            relationship: 'supports',
            weight: 80,
            confidence: 100
        );

        $before =
            $belief
                ->fresh()
                ->confidence;

        $service->addEvidence(
            belief: $belief,
            evidence: $contradiction,
            relationship: 'contradicts',
            weight: 60,
            confidence: 100
        );

        $after =
            $belief
                ->fresh()
                ->confidence;

        $this->assertLessThan(
            $before,
            $after
        );

        $this->assertTrue(
            app(
                BusinessBeliefContradictionService::class
            )->hasConflict(
                $belief->fresh()
            )
        );
    }
}
