<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Enums\BusinessMemoryObservationType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMemoryExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_business_signals_are_extracted_from_memory(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute($client);

        $entry = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Meeting,
            content: implode(' ', [
                'They need a CRM that can handle another location.',
                'Mary is worried about cyber insurance.',
                'I promised to send a proposal next week.',
                'They asked about AI automation.',
            ]),
            source: 'meeting_note',
            confidence: 95
        );

        $items = app(
            BusinessMemoryExtractionService::class
        )->extract($entry);

        $this->assertCount(
            4,
            $items
        );

        $this->assertTrue(
            $items->contains(
                fn ($item) => $item->observation_type
                    === BusinessMemoryObservationType::Requirement
            )
        );

        $this->assertTrue(
            $items->contains(
                fn ($item) => $item->observation_type
                    === BusinessMemoryObservationType::Concern
            )
        );

        $this->assertTrue(
            $items->contains(
                fn ($item) => $item->observation_type
                    === BusinessMemoryObservationType::Promise
            )
        );

        $this->assertTrue(
            $items->contains(
                fn ($item) => $item->observation_type
                    === BusinessMemoryObservationType::Opportunity
            )
        );

        $this->assertSame(
            4,
            $entry
                ->observations()
                ->count()
        );
    }
}
