<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefContextBuilder;
use App\Models\Client;
use Tests\TestCase;

class MorningBriefContextBuilderTest extends TestCase
{
    public function test_morning_brief_context_is_created_from_client(): void
    {
        $client = new Client;

        $client->name = 'Walker';

        $context =
            app(
                MorningBriefContextBuilder::class
            )->build(
                $client
            );

        $this->assertSame(
            'Walker',
            $context->client
        );

        $this->assertNotNull(
            $context->recovery
        );

        $this->assertNotNull(
            $context->allocation
        );
    }
}
