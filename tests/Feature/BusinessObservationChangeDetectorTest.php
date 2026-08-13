<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Observations\BusinessObservation;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationChangeDetector;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshot;
use Tests\TestCase;

class BusinessObservationChangeDetectorTest extends TestCase
{
    public function test_observation_changes_are_detected(): void
    {
        $previous =
            new BusinessObservationSnapshot(
                observations: collect([
                    new BusinessObservation(
                        type: 'collections',

                        title: 'Client has overdue money',

                        message: '£5,000 is overdue.',

                        priority: 85,

                        clientId: 'client-1',

                        client: 'Test Client',

                        value: 5000,

                        confidence: 100
                    ),
                ]),

                generatedAt: now()
                    ->subDay()
            );

        $current =
            new BusinessObservationSnapshot(
                observations: collect([
                    new BusinessObservation(
                        type: 'collections',

                        title: 'Client has overdue money',

                        message: '£7,000 is overdue.',

                        priority: 90,

                        clientId: 'client-1',

                        client: 'Test Client',

                        value: 7000,

                        confidence: 100
                    ),

                    new BusinessObservation(
                        type: 'billing_dormancy',

                        title: 'Client has gone quiet',

                        message: 'No invoice for 90 days.',

                        priority: 70,

                        clientId: 'client-2',

                        client: 'Dormant Client',

                        value: null,

                        confidence: 90
                    ),
                ]),

                generatedAt: now()
            );

        $changes =
            app(
                BusinessObservationChangeDetector::class
            )->compare(
                $previous,
                $current
            );

        $this->assertSame(
            'worsened',
            $changes->first()->type
        );

        $this->assertTrue(
            $changes->contains(
                fn ($change) => $change->type === 'new'
            )
        );
    }
}
