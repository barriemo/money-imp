<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Extraction\BusinessMemoryExtractionService;
use App\Domains\BusinessMemory\Theories\BusinessMemoryTheoryService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMemoryTheoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_observations_create_working_theory(): void
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

        $theories = app(
            BusinessMemoryTheoryService::class
        )->rebuild(
            $memory
        );

        $this->assertCount(
            1,
            $theories
        );

        $theory =
            $theories->first();

        $this->assertSame(
            'business_expansion',
            $theory->theory_type
        );

        $this->assertSame(
            'Client appears to be expanding to another location.',
            $theory->statement
        );

        $this->assertGreaterThanOrEqual(
            70,
            $theory->confidence
        );

        $this->assertSame(
            2,
            $theory
                ->observations()
                ->count()
        );
    }
}
