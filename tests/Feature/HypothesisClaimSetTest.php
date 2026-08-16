<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\PaymentHypothesisClaimBuilder;
use Tests\TestCase;

class HypothesisClaimSetTest extends TestCase
{
    public function test_payment_assertion_is_decomposed_into_independent_claims(): void
    {
        $hypothesis =
            new Hypothesis(
                statement: 'Those large invoices were paid into our old HSBC account.',
                subjectType: 'client',
                subjectId: 'peak'
            );

        $claims =
            app(
                PaymentHypothesisClaimBuilder::class
            )->build(
                $hypothesis
            );

        $this->assertNotNull(
            $claims->find(
                'payment_occurred'
            )
        );

        $this->assertNotNull(
            $claims->find(
                'payment_received'
            )
        );

        $this->assertNotNull(
            $claims->find(
                'payment_destination_hsbc'
            )
        );
    }
}
