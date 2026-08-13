<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Attention\Context\AttentionContext;
use App\Domains\BusinessBrain\Interrogation\BusinessInterrogator;
use App\Domains\BusinessBrain\Interrogation\BusinessQuestion;
use App\Domains\CommercialTruth\Recovery\RecoveryOpportunitySummary;
use App\Domains\ResourceIntelligence\Allocation\Summary\AllocationVarianceSummary;
use App\Domains\VATIntelligence\VATExposure;
use Tests\TestCase;

class BusinessInterrogatorTest extends TestCase
{
    public function test_business_can_answer_where_are_we(): void
    {
        $answer =
            app(
                BusinessInterrogator::class
            )->ask(
                new BusinessQuestion(
                    'where are we?'
                ),

                new AttentionContext(
                    client: 'Walker',

                    recovery: new RecoveryOpportunitySummary(
                        clientId: 'Walker',

                        opportunityCount: 2,

                        totalValue: 5000,

                        highestValue: 3000,

                        confidence: 90
                    ),

                    allocation: new AllocationVarianceSummary(
                        totalOverrunHours: 25,

                        totalCostExposure: 1625,

                        highestRiskResource: 'John Smith',

                        attentionRequired: true
                    ),

                    vat: new VATExposure(
                        liability: 30000,

                        priority: 100,

                        reason: 'VAT liability requires cash planning.'
                    )
                )
            );

        $this->assertSame(
            36625.0,
            $answer->facts['total_exposure']
        );

        $this->assertSame(
            5000.0,
            $answer->facts['recovery_value']
        );

        $this->assertSame(
            1625.0,
            $answer->facts['allocation_exposure']
        );

        $this->assertSame(
            30000.0,
            $answer->facts['vat_exposure']
        );

        $this->assertSame(
            'vat_exposure',
            $answer->facts['highest_priority_type']
        );

        $this->assertCount(
            3,
            $answer->evidence
        );
    }
}
