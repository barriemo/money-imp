<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Domains\BusinessMemory\Services\BusinessMemoryTimelineService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMemoryTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_memory_entries_are_returned_as_a_timeline(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $add = app(
            AddBusinessMemoryEntry::class
        );

        $add->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Note,
            content: 'Older note',
            occurredAt: now()->subDays(2)
        );

        $add->execute(
            memory: $memory,
            type: BusinessMemoryEntryType::Meeting,
            content: 'Newest meeting',
            occurredAt: now()
        );

        $timeline = app(
            BusinessMemoryTimelineService::class
        )->timeline(
            $memory
        );

        $this->assertCount(
            2,
            $timeline
        );

        $this->assertSame(
            'Newest meeting',
            $timeline
                ->first()
                ->content
        );

        $this->assertSame(
            'Older note',
            $timeline
                ->last()
                ->content
        );
    }
}
