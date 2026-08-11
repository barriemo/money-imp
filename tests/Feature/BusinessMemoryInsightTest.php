<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Enums\BusinessMemoryInsightType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Domains\BusinessMemory\Insights\BusinessMemoryInsightService;
use App\Domains\BusinessMemory\Theories\BusinessMemoryTheoryService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMemoryInsightTest extends TestCase
{
    use RefreshDatabase;

    public function test_memory_generates_actionable_insights(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute($client);

        $add = app(
            AddBusinessMemoryEntry::class
        );

        $extract = app(
            BusinessMemoryExtractionService::class
        );

        foreach ([
            'They need a CRM that can handle another location.',
            'They are considering opening a second location.',
            'I promised to send a proposal next week.',
        ] as $content) {
            $entry = $add->execute(
                memory: $memory,
                type: BusinessMemoryEntryType::Meeting,
                content: $content,
                source: 'meeting_note',
                confidence: 95
            );

            $extract->extract(
                $entry
            );
        }

        app(
            BusinessMemoryTheoryService::class
        )->rebuild(
            $memory
        );

        $insights = app(
            BusinessMemoryInsightService::class
        )->rebuild(
            $memory
        );

        $this->assertTrue(
            $insights->contains(
                fn ($insight) => $insight->insight_type
                    === BusinessMemoryInsightType::Opportunity
                    && $insight->title
                    === 'Review expansion requirements'
            )
        );

        $this->assertTrue(
            $insights->contains(
                fn ($insight) => $insight->insight_type
                    === BusinessMemoryInsightType::FollowUp
            )
        );

        $this->assertDatabaseHas(
            'business_memory_insights',
            [
                'business_memory_id' => $memory->id,

                'title' => 'Promise requires follow-up',

                'status' => 'open',
            ]
        );
    }
}
