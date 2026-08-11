<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\BusinessMemory\Context\BusinessContextService;
use App\Domains\BusinessMemory\Enums\BusinessContextType;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_durable_client_context_can_be_remembered_and_updated(): void
    {
        $client =
            Client::factory()->create();

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $context = app(
            BusinessContextService::class
        );

        $first = $context->remember(
            memory: $memory,
            type: BusinessContextType::CommercialPreference,
            key: 'buying_behaviour',
            value: 'Client prefers spending where it clearly saves staff time.',
            confidence: 90,
            verified: true,
            source: 'owner_context'
        );

        $second = $context->remember(
            memory: $memory,
            type: BusinessContextType::CommercialPreference,
            key: 'buying_behaviour',
            value: 'Client strongly favours investments that save staff time.',
            confidence: 95,
            verified: true,
            source: 'owner_context'
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            'Client strongly favours investments that save staff time.',
            $second->value
        );

        $this->assertSame(
            95,
            $second->confidence
        );

        $this->assertCount(
            1,
            $context->active(
                $memory
            )
        );
    }
}
