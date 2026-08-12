<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\Splitting\WorkActivitySplitter;
use App\Domains\WorkIntelligence\WorkObservation;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use Tests\TestCase;

class WorkActivitySplitterTest extends TestCase
{
    public function test_conversation_can_create_multiple_work_activities(): void
    {
        $observations =
            new WorkObservationCollection(
                collect([
                    new WorkObservation(
                        type: 'work_described',
                        value: 'Fixed Walker CRM integration',
                        confidence: 90,
                        metadata: [
                            'minutes' => 120,
                        ]
                    ),

                    new WorkObservation(
                        type: 'work_described',
                        value: 'Configured analytics tracking',
                        confidence: 85,
                        metadata: [
                            'minutes' => 60,
                        ]
                    ),
                ])
            );

        $activities =
            app(
                WorkActivitySplitter::class
            )
                ->split(
                    $observations
                );

        $this->assertCount(
            2,
            $activities->items
        );

        $this->assertSame(
            'Fixed Walker CRM integration',
            $activities->items
                ->first()
                ->description
        );

        $this->assertSame(
            120,
            $activities->items
                ->first()
                ->minutes
        );
    }
}
