<?php

namespace Tests\Feature;

use App\Domains\EvidenceAcquisition\Scoring\EvidencePriorityCalculator;
use Tests\TestCase;

class EvidencePriorityCalculatorTest extends TestCase
{
    public function test_large_unknown_financial_position_scores_highly(): void
    {
        $score =
            app(
                EvidencePriorityCalculator::class
            )
                ->calculate(
                    impact: 100,

                    confidence: 0,

                    financialValue: 177461,

                    urgency: 80
                );

        $this->assertGreaterThanOrEqual(
            90,
            $score
        );
    }

    public function test_low_value_known_item_scores_lower(): void
    {
        $score =
            app(
                EvidencePriorityCalculator::class
            )
                ->calculate(
                    impact: 30,

                    confidence: 80,

                    financialValue: 75,

                    urgency: 10
                );

        $this->assertLessThan(
            60,
            $score
        );
    }
}
