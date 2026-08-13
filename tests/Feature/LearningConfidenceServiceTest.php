<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Learning\LearningConfidenceService;
use Tests\TestCase;

class LearningConfidenceServiceTest extends TestCase
{
    public function test_learning_is_not_used_before_minimum_sample_size(): void
    {
        $service =
            app(
                LearningConfidenceService::class
            );

        $small =
            $service->forSample(
                4
            );

        $this->assertFalse(
            $small->usable
        );

        $this->assertSame(
            0,
            $small->confidence
        );

        $usable =
            $service->forSample(
                10
            );

        $this->assertTrue(
            $usable->usable
        );

        $this->assertSame(
            75,
            $usable->confidence
        );
    }
}
