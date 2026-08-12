<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\BillingCadenceEngine;
use Tests\TestCase;

class CommercialAgreementCadenceTest extends TestCase
{
    public function test_monthly_history_is_inferred_as_monthly(): void
    {
        $observations = collect([
            (object) [
                'invoice_date' => '2026-05-31',

                'unit_price' => 75,
            ],

            (object) [
                'invoice_date' => '2026-06-30',

                'unit_price' => 75,
            ],

            (object) [
                'invoice_date' => '2026-07-31',

                'unit_price' => 75,
            ],
        ]);

        $result = app(
            BillingCadenceEngine::class
        )->infer(
            $observations
        );

        $this->assertSame(
            'monthly',
            $result['cadence']
        );

        $this->assertSame(
            75.0,
            $result[
                'monthly_equivalent'
            ]
        );
    }

    public function test_annual_history_is_normalised_to_monthly_equivalent(): void
    {
        $observations = collect([
            (object) [
                'invoice_date' => '2025-10-01',

                'unit_price' => 900,
            ],

            (object) [
                'invoice_date' => '2026-10-01',

                'unit_price' => 900,
            ],
        ]);

        $result = app(
            BillingCadenceEngine::class
        )->infer(
            $observations
        );

        $this->assertSame(
            'annual',
            $result['cadence']
        );

        $this->assertSame(
            75.0,
            $result[
                'monthly_equivalent'
            ]
        );
    }

    public function test_single_invoice_is_not_assumed_to_be_recurring(): void
    {
        $result = app(
            BillingCadenceEngine::class
        )->infer(
            collect([
                (object) [
                    'invoice_date' => '2026-07-31',

                    'unit_price' => 525,
                ],
            ])
        );

        $this->assertSame(
            'one_off',
            $result['cadence']
        );

        $this->assertSame(
            0.0,
            $result[
                'monthly_equivalent'
            ]
        );
    }
}
