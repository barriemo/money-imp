<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\AddBusinessMemoryEntry;
use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Enums\BusinessMemoryEntryType;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_have_persistent_business_memory(): void
    {
        $client =
            Client::factory()->create();

        $action = app(
            CreateBusinessMemory::class
        );

        $first = $action->execute(
            $client
        );

        $second = $action->execute(
            $client
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $entry = app(
            AddBusinessMemoryEntry::class
        )->execute(
            memory: $first,
            type: BusinessMemoryEntryType::Meeting,
            content: 'Client mentioned opening a second location.',
            source: 'meeting_note',
            confidence: 95,
            verified: false
        );

        $this->assertSame(
            $first->id,
            $entry->business_memory_id
        );

        $this->assertSame(
            BusinessMemoryEntryType::Meeting,
            $entry->entry_type
        );

        $this->assertSame(
            95,
            $entry->confidence
        );
    }
}
