<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Investigation\Claims\HypothesisClaimAssessmentService;
use App\Domains\BusinessBrain\Investigation\EvidenceItem;
use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\PaymentTruth\Investigation\PaymentHypothesisClaimBuilder;
use Tests\TestCase;

class HypothesisClaimAssessmentServiceTest extends TestCase
{
    public function test_payment_hypothesis_claims_are_assessed_independently(): void
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

        $evidence = [
            new EvidenceItem(
                source: 'accounting',
                description: 'Accounting records the invoices as paid.',
                position: 'supports',
                confidence: 90
            ),

            new EvidenceItem(
                source: 'bank',
                description: 'Canonical bank cash is below accounting-paid value.',
                position: 'neutral',
                confidence: 95
            ),

            new EvidenceItem(
                source: 'bank_coverage',
                description: 'Current bank evidence may be incomplete.',
                position: 'missing',
                confidence: 95
            ),

            new EvidenceItem(
                source: 'bank_source',
                description: 'No HSBC account is represented.',
                position: 'missing',
                confidence: 100
            ),
        ];

        $assessed =
            app(
                HypothesisClaimAssessmentService::class
            )->assess(
                $claims,
                $evidence
            );

        $this->assertSame(
            'supported',
            $assessed
                ->find(
                    'payment_occurred'
                )
                ->status
        );

        $this->assertSame(
            'plausible',
            $assessed
                ->find(
                    'payment_received'
                )
                ->status
        );

        $this->assertSame(
            'unverified',
            $assessed
                ->find(
                    'payment_destination_hsbc'
                )
                ->status
        );
    }
}
