<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Review\CharlieReviewEngine;
use App\Models\CharlieFinding;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieFindingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_previous_findings_are_not_left_open_after_new_review(): void
    {
        $client =
            Client::factory()->create();

        $reviews =
            app(
                CharlieReviewEngine::class
            );

        $first =
            $reviews->review(
                $client
            );

        $firstOpenCount =
            $first
                ->findings
                ->where(
                    'status',
                    'open'
                )
                ->count();

        $this->assertGreaterThan(
            0,
            $firstOpenCount
        );

        $second =
            $reviews->review(
                $client
            );

        $this->assertSame(
            $second
                ->findings
                ->count(),
            CharlieFinding::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->where(
                    'status',
                    'open'
                )
                ->count()
        );

        $this->assertSame(
            0,
            CharlieFinding::query()
                ->where(
                    'charlie_review_id',
                    $first->id
                )
                ->where(
                    'status',
                    'open'
                )
                ->count()
        );
    }
}
