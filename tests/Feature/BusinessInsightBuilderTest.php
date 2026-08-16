<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Insights\BusinessInsightBuilder;
use Tests\TestCase;

class BusinessInsightBuilderTest extends TestCase
{
    public function test_business_insight_can_be_built_with_metrics_risks_and_actions(): void
    {
        $insight =
            app(
                BusinessInsightBuilder::class
            )
                ->headline(
                    'Customer Payment Truth'
                )
                ->status(
                    'needs_attention'
                )
                ->summary(
                    'Customer receipts are known but some remain unallocated.'
                )
                ->metric(
                    'received',
                    '£378,844.37'
                )
                ->metric(
                    'unallocated',
                    '£128,844.37'
                )
                ->risk(
                    'Unallocated receipts may distort debtor reporting.'
                )
                ->action(
                    'Review unallocated customer receipts.'
                )
                ->confidence(
                    80
                )
                ->build();

        $this->assertSame(
            'Customer Payment Truth',
            $insight->headline
        );

        $this->assertSame(
            'needs_attention',
            $insight->status
        );

        $this->assertSame(
            '£378,844.37',
            $insight->metrics['received']
        );

        $this->assertCount(
            1,
            $insight->risks
        );

        $this->assertCount(
            1,
            $insight->actions
        );

        $this->assertSame(
            80,
            $insight->confidence
        );
    }

    public function test_confidence_is_clamped_to_valid_range(): void
    {
        $high =
            app(
                BusinessInsightBuilder::class
            )
                ->confidence(
                    150
                )
                ->build();

        $low =
            app(
                BusinessInsightBuilder::class
            )
                ->confidence(
                    -10
                )
                ->build();

        $this->assertSame(
            100,
            $high->confidence
        );

        $this->assertSame(
            0,
            $low->confidence
        );
    }
}
