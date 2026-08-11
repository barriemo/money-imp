<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Context\Extraction\BusinessContextExtractionService;
use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessContextExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_durable_context_is_extracted_from_owner_background(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $entry = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Note,
            content: implode(' ', [
                'They prefer spending where it saves staff time.',
                'They are considering another nursery.',
                'They think hosting includes backups.',
                'They are interested in automation.',
            ]),
            source: 'owner_context',
            confidence: 95
        );

        $contexts = app(
            BusinessContextExtractionService::class
        )->extract(
            $entry
        );

        $this->assertCount(
            3,
            $contexts
        );

        $this->assertTrue(
            $contexts->contains(
                fn ($context) => $context->context_type
                    === BusinessContextType::CommercialPreference
            )
        );

        $this->assertTrue(
            $contexts->contains(
                fn ($context) => $context->context_type
                    === BusinessContextType::GrowthPlan
            )
        );

        $this->assertTrue(
            $contexts->contains(
                fn ($context) => $context->context_type
                    === BusinessContextType::ServiceExpectation
            )
        );

        $this->assertFalse(
            $contexts->contains(
                fn ($context) => $context->value
                    === 'They are interested in automation.'
            )
        );
    }
}
