<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Review\CharlieFindingEngine;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieFindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_charlie_turns_known_gaps_into_ranked_findings(): void
    {
        $client =
            Client::factory()->create();

        $findings = app(
            CharlieFindingEngine::class
        )->findings(
            $client
        );

        $this->assertNotEmpty(
            $findings
        );

        $this->assertTrue(
            $findings->contains(
                fn (array $finding) => $finding['category']
                    === 'knowledge_gap'
            )
        );

        $this->assertGreaterThan(
            0,
            $findings
                ->first()[
                    'priority_score'
                ]
        );
    }
}
