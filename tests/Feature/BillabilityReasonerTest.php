<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\Analysis\BillabilityReasoner;
use App\Domains\WorkIntelligence\WorkObservation;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BillabilityReasonerTest extends TestCase
{
    public function test_client_work_is_assessed_as_billable(): void
    {
        $observations =
            new WorkObservationCollection(
                collect([
                    new WorkObservation(
                        'client_identified',
                        'MML Law',
                        95
                    ),

                    new WorkObservation(
                        'work_described',
                        'Checkout issue',
                        90
                    ),

                    new WorkObservation(
                        'time_claimed',
                        3,
                        100
                    ),
                ])
            );

        $assessment =
            app(
                BillabilityReasoner::class
            )
                ->assess(
                    $observations
                );

        $this->assertTrue(
            $assessment->billable
        );

        $this->assertGreaterThan(
            80,
            $assessment->confidence
        );

        $this->assertContains(
            'client_identified',
            $assessment->signals
        );
    }

    public function test_non_specific_activity_is_not_billable(): void
    {
        $observations =
            new WorkObservationCollection(
                new Collection([
                    new WorkObservation(
                        'activity_described',
                        'Internal discussion',
                        80
                    ),
                ])
            );

        $assessment =
            app(
                BillabilityReasoner::class
            )
                ->assess(
                    $observations
                );

        $this->assertFalse(
            $assessment->billable
        );
    }
}
