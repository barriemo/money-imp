<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CreditTruth\CreditStatementImportService;
use App\Models\CreditFacility;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditStatementImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_capital_on_tap_batch_becomes_credit_statement_evidence(): void
    {
        $batch =
            ImportBatch::create([
                'source_type' => 'statement',
                'provider' => 'capital_on_tap_pdf',
                'original_filename' => 'statement.pdf',
                'storage_path' => 'imports/statement.pdf',
                'file_hash' => hash(
                    'sha256',
                    'statement.pdf'
                ),
                'status' => 'pending_review',
            ]);

        $evidence =
            app(
                CreditStatementImportService::class
            )->import(
                batch: $batch,
                statement: [
                    'statement_from' => '2026-06-27',
                    'statement_to' => '2026-07-26',
                    'opening_balance' => 30585.51,
                    'closing_balance' => 34351.65,
                    'minimum_payment' => 3435.16,
                    'payment_due_at' => '2026-07-31',
                    'verified' => true,
                    'confidence' => 100,
                ]
            );

        $facility =
            CreditFacility::query()
                ->where(
                    'provider',
                    'capital_on_tap'
                )
                ->firstOrFail();

        $this->assertSame(
            '34351.65',
            $evidence->closing_balance
        );

        $this->assertSame(
            '34351.65',
            $facility->reported_balance
        );

        $this->assertTrue(
            $facility->verified
        );

        $this->assertSame(
            'completed',
            $batch->refresh()->status
        );
    }
}
