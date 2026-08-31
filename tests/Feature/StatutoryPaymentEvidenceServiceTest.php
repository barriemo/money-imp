<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\ObligationTruth\StatutoryPaymentEvidenceService;
use App\Models\BankTransaction;
use Carbon\Carbon;
use Tests\TestCase;

class StatutoryPaymentEvidenceServiceTest extends TestCase
{
    public function test_explicit_hmrc_vat_is_classified_as_vat(): void
    {
        $transaction = $this->transaction(
            description: 'HMRC VAT 4502941120224 VIA MOBILE - PYMT',
            amount: -500,
        );

        $evidence = app(
            StatutoryPaymentEvidenceService::class
        )->classify(
            collect([$transaction])
        )->sole();

        $this->assertSame(
            'hmrc',
            $evidence->authority
        );

        $this->assertSame(
            'vat',
            $evidence->taxType
        );

        $this->assertSame(
            'explicit_tax_type',
            $evidence->classification
        );

        $this->assertSame(
            95,
            $evidence->confidence
        );

        $this->assertSame(
            500.0,
            $evidence->amount
        );

        $this->assertContains(
            'bank_description_explicit_hmrc_vat',
            $evidence->signals
        );
    }

    public function test_generic_hmrc_payment_keeps_tax_type_unresolved(): void
    {
        $transaction = $this->transaction(
            description: 'HMRC CUMBERNAULD 9559526099A00101A',
            amount: -1813.36,
        );

        $evidence = app(
            StatutoryPaymentEvidenceService::class
        )->classify(
            collect([$transaction])
        )->sole();

        $this->assertSame(
            'hmrc',
            $evidence->authority
        );

        $this->assertNull(
            $evidence->taxType
        );

        $this->assertSame(
            'authority_only',
            $evidence->classification
        );

        $this->assertSame(
            90,
            $evidence->confidence
        );

        $this->assertContains(
            'tax_type_unresolved',
            $evidence->signals
        );
    }

    public function test_hmrc_etmp_keeps_tax_type_unresolved(): void
    {
        $transaction = $this->transaction(
            description: '6393 15JAN25 HMRC ETMP GLASGOW GB',
            amount: -415.84,
        );

        $evidence = app(
            StatutoryPaymentEvidenceService::class
        )->classify(
            collect([$transaction])
        )->sole();

        $this->assertNull(
            $evidence->taxType
        );

        $this->assertSame(
            'authority_only',
            $evidence->classification
        );
    }

    public function test_non_hmrc_tax_words_are_not_statutory_payment_evidence(): void
    {
        $transactions = collect([
            $this->transaction(
                description: 'SUMUP*BIRKHILL TAXIS DUNDEE',
                amount: -350,
            ),

            $this->transaction(
                description: 'SECUPRESS VAT NON APPLICA',
                amount: -52.13,
            ),

            $this->transaction(
                description: 'ENVATO SALT LAKE CITY',
                amount: -49.09,
            ),
        ]);

        $evidence = app(
            StatutoryPaymentEvidenceService::class
        )->classify($transactions);

        $this->assertCount(
            0,
            $evidence
        );
    }

    public function test_freeagent_explanation_can_corroborate_vat_tax_type(): void
    {
        $transaction = $this->transaction(
            description: 'HMRC PAYMENT VIA MOBILE',
            amount: -179.53,
        );

        $transaction->raw_payload = [
            'bank_transaction_explanations' => [
                [
                    'description' => 'HMRC VAT 4502941121123 VIA MOBILE - PYMT',

                    'detail' => 'VAT ',
                ],
            ],
        ];

        $evidence = app(
            StatutoryPaymentEvidenceService::class
        )->classify(
            collect([$transaction])
        )->sole();

        $this->assertSame(
            'vat',
            $evidence->taxType
        );

        $this->assertContains(
            'freeagent_explanation_explicit_vat',
            $evidence->signals
        );
    }

    private function transaction(
        string $description,
        float $amount
    ): BankTransaction {
        Carbon::setTestNow(
            '2026-08-31 12:00:00'
        );

        $transaction =
            new BankTransaction;

        $transaction->id =
            fake()->uuid();

        $transaction->transaction_date =
            Carbon::parse('2026-01-28');

        $transaction->amount =
            $amount;

        $transaction->description =
            $description;

        $transaction->raw_payload =
            [];

        return $transaction;
    }
}
