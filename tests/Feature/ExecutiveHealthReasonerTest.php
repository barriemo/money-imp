<?php

namespace Tests\Feature;

use App\Domains\Executive\ExecutiveHealthReasoner;
use App\Domains\Executive\ExecutiveQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveHealthReasonerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_contracted_value_is_not_reported_as_zero(): void
    {
        $answer =
            app(
                ExecutiveHealthReasoner::class
            )->answer(
                ExecutiveQuestion::canKeepLightsOn()
            );

        $this->assertArrayHasKey(
            'recurring_monthly_equivalent',
            $answer->metrics
        );

        /*
         * Unknown contracted value must remain unknown.
         *
         * It must never be coerced to zero merely because
         * contracted commercial truth is not established.
         */
        $this->assertNull(
            $answer->metrics[
                'recurring_monthly_equivalent'
            ]
        );

        $this->assertContains(
            'Contracted commercial terms are not yet established.',
            $answer->missingEvidence
        );
    }

    public function test_business_viability_question_returns_supported_answer(): void
    {
        $answer = app(
            ExecutiveHealthReasoner::class
        )->answer(
            ExecutiveQuestion::canKeepLightsOn()
        );

        $this->assertSame(
            'can_keep_lights_on',
            $answer->questionType
        );

        $this->assertContains(
            $answer->assessment,
            [
                'YES',
                'NO',
                'INCOMPLETE',
            ]
        );

        $this->assertGreaterThanOrEqual(
            0,
            $answer->confidence
        );

        $this->assertLessThanOrEqual(
            100,
            $answer->confidence
        );
    }
}
