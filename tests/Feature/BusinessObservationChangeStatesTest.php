<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Observations\BusinessObservation;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationChangeDetector;
use App\Domains\BusinessBrain\Observations\History\BusinessObservationSnapshot;
use Tests\TestCase;

class BusinessObservationChangeStatesTest extends TestCase
{
    public function test_all_observation_change_states_are_detected(): void
    {
        $previous =
            new BusinessObservationSnapshot(
                observations: collect([
                    new BusinessObservation(
                        type: 'collections',
                        title: 'Worsening Client',
                        message: '£5,000 overdue.',
                        priority: 80,
                        clientId: 'client-1',
                        client: 'Worsening Client',
                        value: 5000,
                        confidence: 100
                    ),

                    new BusinessObservation(
                        type: 'collections',
                        title: 'Improving Client',
                        message: '£10,000 overdue.',
                        priority: 95,
                        clientId: 'client-2',
                        client: 'Improving Client',
                        value: 10000,
                        confidence: 100
                    ),

                    new BusinessObservation(
                        type: 'collections',
                        title: 'Resolved Client',
                        message: '£2,000 overdue.',
                        priority: 75,
                        clientId: 'client-3',
                        client: 'Resolved Client',
                        value: 2000,
                        confidence: 100
                    ),
                ]),
                generatedAt: now()->subDay()
            );

        $current =
            new BusinessObservationSnapshot(
                observations: collect([
                    new BusinessObservation(
                        type: 'collections',
                        title: 'Worsening Client',
                        message: '£7,000 overdue.',
                        priority: 90,
                        clientId: 'client-1',
                        client: 'Worsening Client',
                        value: 7000,
                        confidence: 100
                    ),

                    new BusinessObservation(
                        type: 'collections',
                        title: 'Improving Client',
                        message: '£4,000 overdue.',
                        priority: 85,
                        clientId: 'client-2',
                        client: 'Improving Client',
                        value: 4000,
                        confidence: 100
                    ),

                    new BusinessObservation(
                        type: 'billing_dormancy',
                        title: 'New Dormant Client',
                        message: 'No invoice for 90 days.',
                        priority: 70,
                        clientId: 'client-4',
                        client: 'New Dormant Client',
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

        $this->assertTrue(
            $changes->contains(
                fn ($change) => $change->type === 'new'
            )
        );

        $this->assertTrue(
            $changes->contains(
                fn ($change) => $change->type === 'worsened'
            )
        );

        $this->assertTrue(
            $changes->contains(
                fn ($change) => $change->type === 'improved'
            )
        );

        $this->assertTrue(
            $changes->contains(
                fn ($change) => $change->type === 'resolved'
            )
        );
    }
}
