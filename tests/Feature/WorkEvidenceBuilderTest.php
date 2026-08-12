<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\Analysis\BillabilityAssessment;
use App\Domains\WorkIntelligence\Evidence\WorkEvidenceBuilder;
use App\Domains\WorkIntelligence\WorkObservation;
use App\Domains\WorkIntelligence\WorkObservationCollection;
use Tests\TestCase;

class WorkEvidenceBuilderTest extends TestCase
{
    public function test_billable_work_creates_evidence(): void
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
                        'checkout issue',
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
            new BillabilityAssessment(
                true,
                85,
                'Client-specific work',
                [
                    'client_identified',
                    'specific_work',
                    'time_recorded',
                ]
            );

        $evidence =
            app(
                WorkEvidenceBuilder::class
            )
                ->build(
                    $observations,
                    $assessment
                );

        $this->assertNotNull(
            $evidence
        );

        $this->assertSame(
            'client_work_completed',
            $evidence->type
        );

        $this->assertSame(
            'MML Law',
            $evidence->subject
        );

        $this->assertSame(
            3,
            $evidence->metadata['hours']
        );
    }
}
