<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\ObligationTruth\StatutorySettlementEvidence;
use Tests\TestCase;

class StatutorySettlementEvidenceServiceTest extends TestCase
{
    public function test_statutory_payment_evidence_is_separate_from_current_liability_truth(): void
    {
        $evidence = new StatutorySettlementEvidence(
            [
                'vat' => [
                    'payments_observed' => true,
                    'amount' => 58031.76,
                    'transactions' => 86,
                ],
                'paye_ni' => [
                    'payments_observed' => true,
                    'amount' => 12967.21,
                    'transactions' => 22,
                ],
            ],
            70998.97,
            true
        );

        $this->assertTrue($evidence->paymentEvidenceExists);

        $this->assertSame(
            58031.76,
            $evidence->categories['vat']['amount']
        );

        $this->assertSame(
            12967.21,
            $evidence->categories['paye_ni']['amount']
        );

        $this->assertSame(
            70998.97,
            $evidence->totalObservedAmount
        );
    }
}
