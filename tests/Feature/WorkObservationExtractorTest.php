<?php

namespace Tests\Feature;

use App\Domains\WorkIntelligence\WorkObservationExtractor;
use Tests\TestCase;

class WorkObservationExtractorTest extends TestCase
{
    public function test_extracts_work_observations_from_conversation(): void
    {
        $observations =
            app(
                WorkObservationExtractor::class
            )
                ->extract(
                    'Spent 3 hours fixing the MML Law website checkout issue.'
                );

        $this->assertCount(
            2,
            $observations->items
        );

        $this->assertTrue(
            $observations
                ->items
                ->contains(
                    fn ($observation) => $observation->type === 'time_claimed'
                        && $observation->value === 3
                )
        );

        $this->assertTrue(
            $observations
                ->items
                ->contains(
                    fn ($observation) => $observation->type === 'client_identified'
                        && $observation->value === 'MML Law'
                )
        );
    }
}
