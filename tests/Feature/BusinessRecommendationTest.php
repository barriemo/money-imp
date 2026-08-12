<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessRecommendation;
use Tests\TestCase;

class BusinessRecommendationTest extends TestCase
{
    public function test_recommendation_has_priority_and_confidence(): void
    {
        $recommendation =
            new BusinessRecommendation(
                title: 'Verify RBS balance',
                reason: 'Cash position cannot be trusted yet.',
                priority: 100,
                confidence: 100
            );

        $this->assertSame(
            100,
            $recommendation->priority
        );

        $this->assertSame(
            100,
            $recommendation->confidence
        );
    }
}
