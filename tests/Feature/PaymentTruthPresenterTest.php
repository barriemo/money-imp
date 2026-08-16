<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Position\PaymentTruthPosition;
use App\Domains\BusinessBrain\PaymentTruth\Presentation\PaymentTruthPresenter;
use Tests\TestCase;

class PaymentTruthPresenterTest extends TestCase
{
    public function test_payment_truth_is_presented_for_cfo_view(): void
    {
        $position =
            new PaymentTruthPosition(
                canonicalReceived: 378844.37,
                allocatedReceived: 100000,
                suggestedReceived: 24717.79,
                unmatchedReceived: 254126.58,
                paymentCount: 1155,
                allocatedPaymentCount: 300,
                suggestedPaymentCount: 62,
                unmatchedPaymentCount: 793,
                duplicateEvidenceGroups: 87,
                duplicateEvidenceValue: 31388,
                confidence: 65
            );

        $presentation =
            app(
                PaymentTruthPresenter::class
            )->present(
                $position
            );

        $this->assertSame(
            'Customer Payment Truth',
            $presentation['headline']
        );

        $this->assertSame(
            '£378,844.37',
            $presentation['received']
        );

        $this->assertSame(
            '£24,717.79',
            $presentation['suggested']
        );

        $this->assertSame(
            '£254,126.58',
            $presentation['unmatched']
        );

        $this->assertSame(
            '65%',
            $presentation['confidence']
        );
    }
}
