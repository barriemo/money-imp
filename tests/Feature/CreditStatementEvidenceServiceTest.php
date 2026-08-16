<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CreditTruth\CreditStatementEvidenceService;
use App\Models\CreditFacility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditStatementEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_statement_evidence_updates_credit_facility_truth(): void
    {
        $facility =
            CreditFacility::create([
                'provider' => 'capital_on_tap',
                'name' => 'Capital on Tap',
                'facility_type' => 'business_credit_card',
                'currency' => 'GBP',
                'status' => 'active',
            ]);

        $service =
            app(
                CreditStatementEvidenceService::class
            );

        $service->record(
            facility: $facility,
            evidence: [
                'statement_from' => '2026-06-27',
                'statement_to' => '2026-07-26',
                'opening_balance' => 30585.51,
                'closing_balance' => 34351.65,
                'minimum_payment' => 3435.16,
                'payment_due_at' => '2026-07-31',
                'source' => 'capital_on_tap_pdf',
                'verified' => true,
                'confidence' => 100,
            ]
        );

        $facility =
            $facility->refresh();

        $this->assertSame(
            '34351.65',
            $facility->reported_balance
        );

        $this->assertSame(
            '3435.16',
            $facility->minimum_payment
        );

        $this->assertTrue(
            $facility->verified
        );

        $this->assertSame(
            100,
            $facility->confidence
        );

        $this->assertSame(
            '2026-07-26',
            $facility
                ->reported_balance_at
                ->toDateString()
        );
    }
}
