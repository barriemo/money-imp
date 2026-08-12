<?php

namespace Tests\Feature;

use App\Domains\EvidenceAcquisition\EvidenceAcquisitionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceAcquisitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_returns_ranked_questions(): void
    {
        $questions =
            app(
                EvidenceAcquisitionEngine::class
            )->questions();

        $this->assertNotEmpty(
            $questions
        );

        $this->assertGreaterThan(
            0,
            $questions->first()->priority
        );

        $priorities =
            $questions
                ->pluck('priority')
                ->values();

        $this->assertSame(
            $priorities
                ->sortDesc()
                ->values()
                ->all(),
            $priorities->all()
        );

        $this->assertStringContainsString(
            'bank',
            strtolower(
                $questions
                    ->first()
                    ->question
            )
        );
    }
}
