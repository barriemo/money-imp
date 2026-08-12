<?php

namespace Tests\Feature;

use App\Domains\EvidenceAcquisition\Providers\FinancialEvidenceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialEvidenceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_creates_cash_question_when_truth_is_unknown(): void
    {
        $questions =
            app(
                FinancialEvidenceProvider::class
            )
            ->questions();

        $this->assertTrue(
            $questions->isNotEmpty()
        );

        $question =
            $questions->first();

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
