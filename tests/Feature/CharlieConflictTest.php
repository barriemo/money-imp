<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\CheerfulCharlie\Beliefs\BusinessBeliefEvidenceIngestionService;
use App\Domains\CheerfulCharlie\Conflicts\CharlieConflictService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_charlie_surfaces_active_belief_conflict(): void
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
            confidence: 100,
            summary: 'Supplier evidence suggests Acronis is involved in backup.'
        );

        $conflicts = app(
            CharlieConflictService::class
        )->forSubject(
            $client
        );

        $this->assertCount(
            1,
            $conflicts
        );

        $this->assertSame(
            'Dave at XYZ IT',
            $conflicts
                ->first()[
                    'current_value'
                ]
        );

        $this->assertStringContainsString(
            'Acronis',
            $conflicts
                ->first()[
                    'message'
                ]
        );

        $this->assertLessThan(
            100,
            $conflicts
                ->first()[
                    'confidence'
                ]
        );
    }
}
