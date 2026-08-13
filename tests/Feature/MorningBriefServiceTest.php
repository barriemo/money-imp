<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\MorningBrief\Services\MorningBriefService;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use Tests\TestCase;

class MorningBriefServiceTest extends TestCase
{
    public function test_builds_morning_brief_from_business_context(): void
    {
        $brief =
            app(
                MorningBriefService::class
            )->build(
                new AttentionContext(
                    client: 'Walker',

                    recovery: new RecoveryOpportunitySummary(
                        clientId: 'Walker',

                        opportunityCount: 1,

                        totalValue: 5000,

                        highestValue: 5000,

                        confidence: 95
                    )
                )
            );

        $this->assertCount(
            1,
            $brief->signals
        );

        $this->assertSame(
            'recovery',
            $brief->signals->first()->type
        );
    }
}
