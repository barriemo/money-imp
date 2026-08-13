<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\MorningBrief\Context\MorningBriefBusinessResolver;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorningBriefBusinessResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_businesses_for_morning_brief(): void
    {
        Client::factory()->create([
            'name' => 'Walker',
        ]);

        $clients =
            app(
                MorningBriefBusinessResolver::class
            )->resolve();

        $this->assertCount(
            1,
            $clients
        );

        $this->assertSame(
            'Walker',
            $clients->first()->name
        );
    }
}
