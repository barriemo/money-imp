<?php

namespace Tests\Feature;

use App\Domains\TimelineTruth\ChangeDetectionService;
use App\Models\Client;
use App\Models\TimelineEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineChangeDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_creates_timeline_event(): void
    {
        $client =
            Client::factory()->create();

        $event = app(
            ChangeDetectionService::class
        )->detect(
            subjectType: 'client',

            subjectId: $client->id,

            field: 'hosting_cadence',

            before: 'one_off',

            after: 'annual',

            source: 'commercial_truth',

            confidenceBefore: 70,

            confidenceAfter: 100
        );

        $this->assertNotNull(
            $event
        );

        $this->assertDatabaseCount(
            'timeline_events',
            1
        );
    }

    public function test_unchanged_truth_does_not_create_noise(): void
    {
        $client =
            Client::factory()->create();

        $event = app(
            ChangeDetectionService::class
        )->detect(
            subjectType: 'client',

            subjectId: $client->id,

            field: 'hosting_cadence',

            before: 'monthly',

            after: 'monthly',

            source: 'commercial_truth',

            confidenceBefore: 99,

            confidenceAfter: 99
        );

        $this->assertNull(
            $event
        );

        $this->assertSame(
            0,
            TimelineEvent::query()
                ->count()
        );
    }
}
