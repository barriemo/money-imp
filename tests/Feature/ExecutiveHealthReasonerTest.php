<?php

namespace Tests\Feature;

use App\Domains\Executive\ExecutiveHealthReasoner;
use App\Domains\Executive\ExecutiveQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveHealthReasonerTest extends TestCase
{
    use RefreshDatabase;

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
