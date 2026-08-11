<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\CheerfulCharlie\Intake\CharlieClientIntakeService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieClientIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_background_flows_into_charlie_brief(): void
    {
        $client =
            Client::factory()->create();

        $result = app(
            CharlieClientIntakeService::class
        )->ingest(
            client: $client,
            content: implode(' ', [
                'They need a CRM that can handle another location.',
                'They are considering opening a second location.',
                'I promised to send a proposal next week.',
                'They asked about AI automation.',
            ]),
            type: BusinessMemoryEntryType::Meeting,
            source: 'meeting_note',
            confidence: 95
        );

        $this->assertSame(
            1,
            $result['brief']['summary']['memory_count']
        );

        $this->assertGreaterThan(
            0,
            $result['observations']->count()
        );

        $this->assertArrayHasKey(
            'contexts',
            $result
        );

        $this->assertGreaterThan(
            0,
            $result['theories']->count()
        );

        $this->assertGreaterThan(
            0,
            $result['insights']->count()
        );

        $this->assertGreaterThan(
            0,
            $result['brief']['priorities']->count()
        );

        $this->assertTrue(
            $result['brief']['priorities']->contains(
                fn ($priority) => $priority->insight->title
                    === 'Promise requires follow-up'
            )
        );
    }
}
