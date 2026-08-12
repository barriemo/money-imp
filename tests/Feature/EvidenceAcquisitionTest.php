<?php

namespace Tests\Feature;

use App\Domains\EvidenceAcquisition\EvidenceAcquisitionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_returns_high_priority_questions(): void
    {
        $questions =
            app(
                EvidenceAcquisitionEngine::class
            )
                ->questions();

        $this->assertNotEmpty(
            $questions
        );

        $this->assertSame(
            100,
            $questions->first()->priority
        );

        $this->assertStringContainsString(
            'bank',
            strtolower(
                $questions->first()->question
            )
        );
    }
}
