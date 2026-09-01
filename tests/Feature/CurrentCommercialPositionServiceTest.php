<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\CurrentCommercialPositionService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCommercialPositionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_separates_current_recent_stale_and_historical_recurring_evidence(): void
    {
        $hostingClient = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $hostingClient,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75,
            );
        }

        $domainClient = Client::factory()->create();

        foreach ([
            '2024-10-01',
            '2025-10-01',
        ] as $date) {
            $this->invoiceItem(
                client: $domainClient,
                date: $date,
                description: 'Domain Annual Renewal - example.com',
                amount: 120,
            );
        }

        $recentClient = Client::factory()->create();

        foreach ([
            '2026-04-30',
            '2026-05-31',
            '2026-06-30',
        ] as $date) {
            $this->invoiceItem(
                client: $recentClient,
                date: $date,
                description: 'Monthly Marketing Retainer',
                amount: 1000,
            );
        }

        $staleClient = Client::factory()->create();

        foreach ([
            '2026-01-31',
            '2026-02-28',
            '2026-03-31',
        ] as $date) {
            $this->invoiceItem(
                client: $staleClient,
                date: $date,
                description: 'Paid Management - PPC',
                amount: 150,
            );
        }

        $historicalClient = Client::factory()->create();

        foreach ([
            '2025-01-31',
            '2025-02-28',
            '2025-03-31',
        ] as $date) {
            $this->invoiceItem(
                client: $historicalClient,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 50,
            );
        }

        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $this->assertSame(
            5,
            $position->serviceCandidateCount
        );

        $this->assertSame(
            5,
            $position->recurringCandidateCount
        );

        $this->assertSame(
            2,
            $position->currentRecurringCandidateCount
        );

        $this->assertSame(
            85.0,
            $position->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->recentlyObservedRecurringCandidateCount
        );

        $this->assertSame(
            1000.0,
            $position->recentlyObservedMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->staleRecurringCandidateCount
        );

        $this->assertSame(
            150.0,
            $position->staleMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $position->historicalRecurringCandidateCount
        );

        $this->assertSame(
            50.0,
            $position->historicalMonthlyEquivalent
        );

        $this->assertSame(
            'invoice_history_supported_not_reconciled',
            $position->evidenceStatus
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_position_breakdowns_reconcile_to_supported_current_value(): void
    {
        $firstClient = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $firstClient,
                date: $date,
                description: 'Monthly Hosting, Security Updates & Backups',
                amount: 75,
            );
        }

        $secondClient = Client::factory()->create();

        foreach ([
            '2026-06-30',
            '2026-07-31',
            '2026-08-31',
        ] as $date) {
            $this->invoiceItem(
                client: $secondClient,
                date: $date,
                description: 'Social Media Retainer',
                amount: 600,
            );
        }

        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $serviceTypeTotal = round(
            collect(
                $position->byServiceType
            )->sum(
                'supported_current_monthly_equivalent'
            ),
            2
        );

        $clientTotal = round(
            collect(
                $position->byClient
            )->sum(
                'supported_current_monthly_equivalent'
            ),
            2
        );

        $this->assertSame(
            675.0,
            $position->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            $position->supportedCurrentMonthlyEquivalent,
            $serviceTypeTotal
        );

        $this->assertSame(
            $position->supportedCurrentMonthlyEquivalent,
            $clientTotal
        );

        $this->assertSame(
            2,
            count(
                $position->byServiceType
            )
        );

        $this->assertSame(
            2,
            count(
                $position->byClient
            )
        );
    }

    public function test_project_and_pass_through_candidates_are_excluded_from_commercial_position(): void
    {
        $client = Client::factory()->create();

        $this->invoiceItem(
            client: $client,
            date: '2026-08-31',
            description: 'Website Design & Development',
            amount: 10000,
        );

        $this->invoiceItem(
            client: $client,
            date: '2026-08-31',
            description: 'Advertising Spend',
            amount: 5000,
        );

        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $this->assertSame(
            0,
            $position->serviceCandidateCount
        );

        $this->assertSame(
            0,
            $position->recurringCandidateCount
        );

        $this->assertSame(
            0.0,
            $position->supportedCurrentMonthlyEquivalent
        );
    }

    public function test_position_preserves_provenance_and_caveats(): void
    {
        $position = app(
            CurrentCommercialPositionService::class
        )->position(
            CarbonImmutable::parse(
                '2026-09-01'
            )
        );

        $this->assertSame(
            'accounting_invoice_items',
            $position->provenance['source']
        );

        $this->assertSame(
            'CommercialServiceFingerprint',
            $position->provenance['classification']
        );

        $this->assertNotEmpty(
            $position->caveats
        );

        $this->assertStringContainsString(
            'not MRR',
            implode(
                ' ',
                $position->caveats
            )
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
