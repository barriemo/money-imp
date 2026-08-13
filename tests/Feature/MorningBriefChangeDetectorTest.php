<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\AttentionSignal;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefChangeDetector;
use App\Domains\BusinessBrain\MorningBrief\History\MorningBriefSnapshot;
use Tests\TestCase;

class MorningBriefChangeDetectorTest extends TestCase
{
    public function test_detects_new_worsened_and_resolved_signals(): void
    {
        $previous =
            new MorningBriefSnapshot(
                client: 'Walker',

                signalCount: 2,

                signals: collect([
                    new AttentionSignal(
                        type: 'vat_exposure',

                        client: 'Walker',

                        priority: 70,

                        value: 20000,

                        reason: 'VAT liability.'
                    ),

                    new AttentionSignal(
                        type: 'recovery',

                        client: 'Walker',

                        priority: 50,

                        value: 5000,

                        reason: 'Recovery opportunity.'
                    ),
                ]),

                generatedAt: now()->subDay()
            );

        $current =
            new MorningBriefSnapshot(
                client: 'Walker',

                signalCount: 2,

                signals: collect([
                    new AttentionSignal(
                        type: 'vat_exposure',

                        client: 'Walker',

                        priority: 90,

                        value: 30000,

                        reason: 'VAT liability.'
                    ),

                    new AttentionSignal(
                        type: 'allocation_variance',

                        client: 'Walker',

                        priority: 60,

                        value: 1500,

                        reason: 'Allocation variance.'
                    ),
                ]),

                generatedAt: now()
            );

        $changes =
            app(
                MorningBriefChangeDetector::class
            )->detect(
                $previous,
                $current
            );

        $this->assertCount(
            3,
            $changes
        );

        $this->assertSame(
            'worsened',
            $changes
                ->firstWhere(
                    'signalType',
                    'vat_exposure'
                )
                ->type
        );

        $this->assertSame(
            'resolved',
            $changes
                ->firstWhere(
                    'signalType',
                    'recovery'
                )
                ->type
        );

        $this->assertSame(
            'new',
            $changes
                ->firstWhere(
                    'signalType',
                    'allocation_variance'
                )
                ->type
        );
    }
}
