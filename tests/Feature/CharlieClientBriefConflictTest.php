<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefEvidenceIngestionService;
use App\Domains\CheerfulCharlie\Briefing\CharlieClientBriefService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieClientBriefConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_brief_surfaces_belief_conflict(): void
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

        $beliefs = app(
            BusinessBeliefEvidenceIngestionService::class
        );

        $beliefs->ingest(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Dave at XYZ IT',
            evidence: $support,
            weight: 90,
            confidence: 100
        );

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

        $beliefs->ingest(
            subject: $client,
            beliefType: 'service_provider',
            key: 'backup_provider',
            value: 'Acronis',
            evidence: $contradiction,
            weight: 80,
            confidence: 100
        );

        $brief = app(
            CharlieClientBriefService::class
        )->build(
            $client
        );

        $this->assertSame(
            1,
            $brief['summary']['conflict_count']
        );

        $this->assertCount(
            1,
            $brief['conflicts']
        );

        $this->assertStringContainsString(
            'Acronis',
            $brief['conflicts']
                ->first()[
                    'message'
                ]
        );
    }
}
