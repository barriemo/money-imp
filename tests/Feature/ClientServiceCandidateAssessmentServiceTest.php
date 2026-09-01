<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\ClientServiceCandidateAssessmentService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceCandidateAssessmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_monthly_evidence_can_establish_current_recurring_value(): void
    {
        $client = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Monthly Hosting',
                amount: 75,
            );
        }

        $assessment = app(
            ClientServiceCandidateAssessmentService::class
        )
            ->forClient(
                $client,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->first();

        $this->assertSame(
            'current',
            $assessment->freshness
        );

        $this->assertTrue(
            $assessment->cadenceEstablished
        );

        $this->assertTrue(
            $assessment->recurringEvidence
        );

        $this->assertSame(
            75.0,
            $assessment
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            'ready_for_review',
            $assessment
                ->promotionReadiness
        );
    }

    public function test_old_monthly_evidence_is_historical_and_not_current_mrr(): void
    {
        $client = Client::factory()->create();

        foreach ([
            '2025-01-31',
            '2025-02-28',
            '2025-03-31',
        ] as $date) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Monthly Hosting',
                amount: 75,
            );
        }

        $assessment = app(
            ClientServiceCandidateAssessmentService::class
        )
            ->forClient(
                $client,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->first();

        $this->assertSame(
            'historical',
            $assessment->freshness
        );

        $this->assertTrue(
            $assessment->recurringEvidence
        );

        $this->assertNull(
            $assessment
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            'needs_more_evidence',
            $assessment
                ->promotionReadiness
        );
    }

    public function test_annual_service_can_still_be_current_after_350_days(): void
    {
        $client = Client::factory()->create();

        foreach ([
            '2024-09-16',
            '2025-09-16',
        ] as $date) {
            $this->invoiceItem(
                client: $client,
                date: $date,
                description: 'Domain Annual Renewal - example.com',
                amount: 120,
            );
        }

        $assessment = app(
            ClientServiceCandidateAssessmentService::class
        )
            ->forClient(
                $client,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->first();

        $this->assertSame(
            'annual',
            $assessment
                ->candidate
                ->cadence
        );

        $this->assertSame(
            'current',
            $assessment->freshness
        );

        $this->assertTrue(
            $assessment->cadenceEstablished
        );

        $this->assertSame(
            10.0,
            $assessment
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            'ready_for_review',
            $assessment
                ->promotionReadiness
        );
    }

    public function test_one_off_service_observation_is_not_treated_as_current_recurring_revenue(): void
    {
        $client = Client::factory()->create();

        $this->invoiceItem(
            client: $client,
            date: '2026-08-31',
            description: 'Paid Management - PPC',
            amount: 300,
        );

        $assessment = app(
            ClientServiceCandidateAssessmentService::class
        )
            ->forClient(
                $client,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->first();

        $this->assertSame(
            'one_off',
            $assessment
                ->candidate
                ->cadence
        );

        $this->assertSame(
            'recently_observed',
            $assessment->freshness
        );

        $this->assertFalse(
            $assessment->cadenceEstablished
        );

        $this->assertFalse(
            $assessment->recurringEvidence
        );

        $this->assertNull(
            $assessment
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            'needs_more_evidence',
            $assessment
                ->promotionReadiness
        );
    }

    public function test_pass_through_candidate_is_never_ready_for_client_service_promotion(): void
    {
        $client = Client::factory()->create();

        $this->invoiceItem(
            client: $client,
            date: '2026-08-31',
            description: 'Advertising Spend',
            amount: 500,
        );

        $assessment = app(
            ClientServiceCandidateAssessmentService::class
        )
            ->forClient(
                $client,
                CarbonImmutable::parse(
                    '2026-09-01'
                )
            )
            ->first();

        $this->assertSame(
            'not_service_candidate',
            $assessment
                ->promotionReadiness
        );

        $this->assertNull(
            $assessment
                ->currentMonthlyEquivalent
        );
    }

    private function invoiceItem(
        Client $client,
        string $date,
        string $description,
        float $amount
    ): void {
        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => (string) str()->uuid(),
            'invoice_date' => $date,
            'status' => 'paid',
        ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'net_amount' => $amount,
        ]);
    }
}
