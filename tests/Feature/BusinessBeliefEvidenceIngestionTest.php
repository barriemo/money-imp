<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefContradictionService;
use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefEvidenceIngestionService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBeliefEvidenceIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_evidence_supports_existing_belief(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $first = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Note,
            content: 'Dave at XYZ IT handles backups.',
            source: 'owner_context',
            confidence: 100,
            verified: true
        );

        $service = app(
            BusinessBeliefEvidenceIngestionService::class
        );

        $belief = $service->ingest(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Dave at XYZ IT',
            evidence: $first,
            weight: 90,
            confidence: 100
        );

        $before =
            $belief->confidence;

        $second = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Meeting,
            content: 'Confirmed Dave at XYZ IT handles backups.',
            source: 'meeting_note',
            confidence: 100,
            verified: true
        );

        $belief = $service->ingest(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Dave at XYZ IT',
            evidence: $second,
            weight: 80,
            confidence: 100
        );

        $this->assertSame(
            'Dave at XYZ IT',
            $belief->value
        );

        $this->assertGreaterThanOrEqual(
            $before,
            $belief->confidence
        );

        $this->assertSame(
            2,
            $belief
                ->evidence()
                ->where(
                    'relationship',
                    'supports'
                )
                ->count()
        );
    }

    public function test_conflicting_evidence_does_not_overwrite_existing_belief(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $support = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Note,
            content: 'Dave at XYZ IT handles backups.',
            source: 'owner_context',
            confidence: 100,
            verified: true
        );

        $service = app(
            BusinessBeliefEvidenceIngestionService::class
        );

        $belief = $service->ingest(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Dave at XYZ IT',
            evidence: $support,
            weight: 90,
            confidence: 100
        );

        $before =
            $belief->confidence;

        $contradiction = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Document,
            content: 'Acronis Backup invoice received.',
            source: 'supplier_invoice',
            confidence: 100,
            verified: true
        );

        $belief = $service->ingest(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Acronis',
            evidence: $contradiction,
            weight: 80,
            confidence: 100,
            summary: 'Supplier evidence suggests Acronis is involved in backup.'
        );

        $this->assertSame(
            'Dave at XYZ IT',
            $belief->value
        );

        $this->assertLessThan(
            $before,
            $belief->confidence
        );

        $this->assertTrue(
            app(
                BusinessBeliefContradictionService::class
            )->hasConflict(
                $belief
            )
        );

        $this->assertDatabaseHas(
            'business_belief_evidence',
            [
                'business_belief_id' => $belief->id,

                'relationship' => 'contradicts',
            ]
        );
    }
}
