<?php

namespace Tests\Feature;

use App\Domains\TimelineTruth\TimelineRecorder;
use App\Domains\TimelineTruth\TimelineTruthService;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_truth_event_can_be_recorded_and_read_back(): void
    {
        $client =
            Client::factory()->create();

        app(
            TimelineRecorder::class
        )->record(
            subjectType: 'client',

            subjectId: $client->id,

            eventType: 'truth_changed',

            source: 'commercial_truth',

            summary: 'Hosting cadence was confirmed as annual.',

            field: 'hosting_cadence',

            before: 'one_off',

            after: 'annual',

            confidenceBefore: 70,

            confidenceAfter: 100
        );

        $events = app(
            TimelineTruthService::class
        )->forSubject(
            'client',
            $client->id
        );

        $this->assertCount(
            1,
            $events
        );

        $event =
            $events->first();

        $this->assertSame(
            'hosting_cadence',
            $event->field
        );

        $this->assertSame(
            'one_off',
            $event->before[
                'value'
            ]
        );

        $this->assertSame(
            'annual',
            $event->after[
                'value'
            ]
        );

        $this->assertSame(
            100,
            $event->confidence_after
        );
    }
}
