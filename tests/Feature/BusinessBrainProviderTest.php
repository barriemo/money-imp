<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessBrainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessBrainProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_brain_asks_for_missing_cash_evidence(): void
    {
        $brain = app(
            BusinessBrainService::class
        )->build();

        $this->assertGreaterThan(
            0,
            $brain->observations->count()
        );

        $question =
            $brain->questions
                ->first();

        $this->assertNotNull(
            $question
        );

        $this->assertSame(
            100,
            $question->priority
        );

        $this->assertStringContainsString(
            'bank',
            strtolower(
                $question->question
            )
        );
    }
}
