<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Domains\BusinessMemory\Insights\BusinessMemoryInsightService;
use App\Domains\CheerfulCharlie\Briefing\CharlieClientBriefService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieClientBriefTest extends TestCase
{
    use RefreshDatabase;

    public function test_charlie_builds_client_brief_from_memory_context_and_insights(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        app(
            BusinessContextService::class
        )->remember(
            memory: $memory,
            type: BusinessContextType::CommercialPreference,
            key: 'buying_behaviour',
            value: 'Client prefers investments that save staff time.',
            confidence: 95,
            verified: true,
            source: 'owner_context'
        );

        $entry = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Meeting,
            content: 'I promised to send a proposal next week.',
            source: 'meeting_note',
            confidence: 95
        );

        app(
            BusinessMemoryExtractionService::class
        )->extract(
            $entry
        );

        app(
            BusinessMemoryInsightService::class
        )->rebuild(
            $memory
        );

        $brief = app(
            CharlieClientBriefService::class
        )->build(
            $client
        );

        $this->assertSame(
            $client->id,
            $brief['client']->id
        );

        $this->assertSame(
            1,
            $brief['summary']['context_count']
        );

        $this->assertSame(
            1,
            $brief['summary']['memory_count']
        );

        $this->assertSame(
            1,
            $brief['summary']['insight_count']
        );

        $this->assertArrayHasKey(
            'conflicts',
            $brief
        );

        $this->assertSame(
            0,
            $brief['summary']['conflict_count']
        );

        $this->assertCount(
            1,
            $brief['priorities']
        );

        $this->assertSame(
            'Promise requires follow-up',
            $brief['priorities']
                ->first()
                ->insight
                ->title
        );
    }
}
