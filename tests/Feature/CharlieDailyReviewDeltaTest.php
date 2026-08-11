<?php

namespace Tests\Feature;

use App\Domains\CheerfulCharlie\Daily\CharlieReviewDeltaService;
use App\Models\CharlieFinding;
use App\Models\CharlieReview;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieDailyReviewDeltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_delta_identifies_new_and_resolved_findings(): void
    {
        $client =
            Client::factory()->create();

        $previous =
            CharlieReview::create([
                'client_id' => $client->id,

                'reviewed_at' => now()->subDay(),

                'finding_count' => 2,

                'high_priority_count' => 1,

                'status' => 'complete',
            ]);

        CharlieFinding::create([
            'charlie_review_id' => $previous->id,

            'client_id' => $client->id,

            'category' => 'knowledge_gap',

            'severity' => 'high',

            'title' => 'Who manages backups?',

            'description' => 'Backup ownership unknown.',

            'confidence' => 100,

            'priority_score' => 83,

            'status' => 'open',
        ]);

        CharlieFinding::create([
            'charlie_review_id' => $previous->id,

            'client_id' => $client->id,

            'category' => 'follow_up',

            'severity' => 'high',

            'title' => 'Promise requires follow-up',

            'description' => 'Proposal promised.',

            'confidence' => 90,

            'priority_score' => 80,

            'status' => 'open',
        ]);

        $current =
            CharlieReview::create([
                'client_id' => $client->id,

                'reviewed_at' => now(),

                'finding_count' => 2,

                'high_priority_count' => 1,

                'status' => 'complete',
            ]);

        CharlieFinding::create([
            'charlie_review_id' => $current->id,

            'client_id' => $client->id,

            'category' => 'follow_up',

            'severity' => 'high',

            'title' => 'Promise requires follow-up',

            'description' => 'Proposal promised.',

            'confidence' => 90,

            'priority_score' => 80,

            'status' => 'open',
        ]);

        CharlieFinding::create([
            'charlie_review_id' => $current->id,

            'client_id' => $client->id,

            'category' => 'opportunity',

            'severity' => 'medium',

            'title' => 'Automation opportunity',

            'description' => 'Client asked about automation.',

            'confidence' => 75,

            'priority_score' => 66,

            'status' => 'open',
        ]);

        $delta = app(
            CharlieReviewDeltaService::class
        )->compare(
            $previous,
            $current
        );

        $this->assertSame(
            1,
            $delta['new_count']
        );

        $this->assertSame(
            1,
            $delta['resolved_count']
        );

        $this->assertSame(
            1,
            $delta['unchanged_count']
        );

        $this->assertSame(
            'Automation opportunity',
            $delta['new']
                ->first()
                ->title
        );
    }
}
