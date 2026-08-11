<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Review\CharlieReviewEngine;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_charlie_review_persists_ranked_findings(): void
    {
        $client =
            Client::factory()->create();

        $review = app(
            CharlieReviewEngine::class
        )->review(
            $client
        );

        $this->assertSame(
            $client->id,
            $review->client_id
        );

        $this->assertGreaterThan(
            0,
            $review->finding_count
        );

        $this->assertSame(
            $review->finding_count,
            $review->findings->count()
        );

        $this->assertDatabaseHas(
            'charlie_reviews',
            [
                'id' => $review->id,

                'client_id' => $client->id,

                'status' => 'complete',
            ]
        );

        $this->assertDatabaseHas(
            'charlie_findings',
            [
                'charlie_review_id' => $review->id,

                'client_id' => $client->id,

                'status' => 'open',
            ]
        );
    }
}
