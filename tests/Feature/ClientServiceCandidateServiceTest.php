<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\ClientServiceCandidateService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServiceCandidateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_service_evidence_builds_read_only_candidate(): void
    {
        $client = Client::factory()->create();

        foreach ([
            '2026-05-31',
            '2026-06-30',
            '2026-07-31',
        ] as $date) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'HOST-'.$date,
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Monthly Hosting, Security Updates & Backups',
                'quantity' => 1,
                'unit_price' => 75,
                'net_amount' => 75,
            ]);
        }

        $candidates = app(
            ClientServiceCandidateService::class
        )->forClient($client);

        $this->assertCount(
            1,
            $candidates
        );

        $candidate = $candidates->first();

        $this->assertSame(
            'hosting',
            $candidate->serviceType
        );

        $this->assertSame(
            'service_candidate',
            $candidate->commercialTreatment
        );

        $this->assertTrue(
            $candidate->isServiceCandidate()
        );

        $this->assertSame(
            3,
            $candidate->evidenceCount
        );

        $this->assertSame(
            225.0,
            $candidate->signedObservedNet
        );

        $this->assertSame(
            'monthly',
            $candidate->cadence
        );

        $this->assertSame(
            75.0,
            $candidate->monthlyEquivalent
        );

        $this->assertSame(
            '2026-05-31',
            $candidate->firstObservedOn
        );

        $this->assertSame(
            '2026-07-31',
            $candidate->lastObservedOn
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_identical_composite_invoice_lines_remain_source_item_atomic(): void
    {
        $client =
            Client::factory()->create();

        foreach (
            [
                '2026-07-31',
                '2026-08-31',
            ] as $date
        ) {
            $invoice =
                AccountingInvoice::create([
                    'client_id' => $client->id,
                    'invoice_number' => 'COMP-'.$date,
                    'invoice_date' => $date,
                    'status' => 'paid',
                ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Consultancy / Support / Website Development / SEO / Content',
                'quantity' => 1,
                'unit_price' => 4000,
                'net_amount' => 4000,
            ]);
        }

        $composite =
            app(
                ClientServiceCandidateService::class
            )
                ->forClient(
                    $client
                )
                ->filter(
                    fn ($candidate) => $candidate
                        ->isCompositeCandidate()
                )
                ->values();

        $this->assertCount(
            2,
            $composite
        );

        $this->assertTrue(
            $composite->every(
                fn ($candidate) => $candidate->evidenceCount
                    === 1
            )
        );

        $this->assertTrue(
            $composite->every(
                fn ($candidate) => count(
                    $candidate->invoiceItemIds
                ) === 1
            )
        );

        /*
         * Classification identity may match, but source evidence
         * must remain separate for future human commercial review.
         */
        $this->assertSame(
            $composite[0]->fingerprint,
            $composite[1]->fingerprint
        );

        $this->assertNotSame(
            $composite[0]->invoiceItemIds[0],
            $composite[1]->invoiceItemIds[0]
        );
    }

    public function test_project_candidates_do_not_collapse_into_one_fake_service(): void
    {
        $client = Client::factory()->create();

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'PROJECTS-1',
            'invoice_date' => '2026-07-31',
            'status' => 'paid',
        ]);

        foreach ([
            [
                'description' => 'V7 Rolex Development Work',
                'net' => 10000,
            ],
            [
                'description' => 'Website Design & Development',
                'net' => 5000,
            ],
        ] as $row) {
            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => $row['description'],
                'quantity' => 1,
                'unit_price' => $row['net'],
                'net_amount' => $row['net'],
            ]);
        }

        $candidates = app(
            ClientServiceCandidateService::class
        )->forClient($client);

        $projects = $candidates->filter(
            fn ($candidate) => $candidate->commercialTreatment
                === 'project_candidate'
        );

        $this->assertCount(
            2,
            $projects
        );

        $this->assertTrue(
            $projects->every(
                fn ($candidate) => ! $candidate
                    ->isServiceCandidate()
            )
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_unknown_descriptions_remain_separate_evidence_groups(): void
    {
        $client = Client::factory()->create();

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'UNKNOWN-1',
            'invoice_date' => '2026-07-31',
            'status' => 'paid',
        ]);

        foreach ([
            'Bespoke unknown alpha',
            'Bespoke unknown beta',
        ] as $description) {
            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => 100,
                'net_amount' => 100,
            ]);
        }

        $unknown = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->filter(
                fn ($candidate) => $candidate->commercialTreatment
                    === 'unknown'
            );

        $this->assertCount(
            2,
            $unknown
        );
    }

    public function test_negative_commercial_adjustment_keeps_its_sign(): void
    {
        $client = Client::factory()->create();

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'ADJUSTMENT-1',
            'invoice_date' => '2026-07-31',
            'status' => 'paid',
        ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'description' => 'Discount',
            'quantity' => 1,
            'unit_price' => -475,
            'net_amount' => -475,
        ]);

        $candidate = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->first();

        $this->assertSame(
            -475.0,
            $candidate->signedObservedNet
        );

        $this->assertSame(
            0.0,
            $candidate->positiveObservedNet
        );

        $this->assertSame(
            -475.0,
            $candidate->negativeObservedNet
        );

        $this->assertFalse(
            $candidate->isServiceCandidate()
        );
    }

    public function test_pass_through_spend_is_not_eligible_for_client_service(): void
    {
        $client = Client::factory()->create();

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'MEDIA-1',
            'invoice_date' => '2026-07-31',
            'status' => 'paid',
        ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'description' => 'PPC - Advertising spend Budget - Google',
            'quantity' => 1,
            'unit_price' => 500,
            'net_amount' => 500,
        ]);

        $candidate = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->first();

        $this->assertSame(
            'media_spend',
            $candidate->serviceType
        );

        $this->assertSame(
            'pass_through_candidate',
            $candidate->commercialTreatment
        );

        $this->assertFalse(
            $candidate->isServiceCandidate()
        );
    }

    public function test_multiple_lines_on_same_date_do_not_distort_cadence(): void
    {
        $client = Client::factory()->create();

        foreach ([
            [
                'date' => '2026-05-31',
                'lines' => 2,
            ],
            [
                'date' => '2026-06-30',
                'lines' => 1,
            ],
            [
                'date' => '2026-07-31',
                'lines' => 1,
            ],
        ] as $period) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'HOST-'.$period['date'],
                'invoice_date' => $period['date'],
                'status' => 'paid',
            ]);

            for (
                $line = 0;
                $line < $period['lines'];
                $line++
            ) {
                AccountingInvoiceItem::create([
                    'accounting_invoice_id' => $invoice->id,
                    'description' => 'Monthly Hosting',
                    'quantity' => 1,
                    'unit_price' => 75,
                    'net_amount' => 75,
                ]);
            }
        }

        $candidate = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->first();

        $this->assertSame(
            4,
            $candidate->evidenceCount
        );

        $this->assertSame(
            'monthly',
            $candidate->cadence
        );

        $this->assertSame(
            75.0,
            $candidate->monthlyEquivalent
        );
    }

    public function test_monthly_catch_up_quantity_does_not_inflate_current_monthly_value(): void
    {
        $client = Client::factory()->create();

        foreach ([
            ['date' => '2025-12-31', 'quantity' => 1],
            ['date' => '2026-01-31', 'quantity' => 1],
            ['date' => '2026-02-28', 'quantity' => 1],
            ['date' => '2026-03-31', 'quantity' => 1],
            ['date' => '2026-04-30', 'quantity' => 1],
            ['date' => '2026-07-31', 'quantity' => 3],
        ] as $row) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'PPC-'.$row['date'],
                'invoice_date' => $row['date'],
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Paid Management - PPC',
                'quantity' => $row['quantity'],
                'unit_price' => 100,
                'net_amount' => 100 * $row['quantity'],
            ]);
        }

        $candidate = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->firstOrFail(
                fn ($row) => $row->serviceType
                    === 'ppc_management'
            );

        $this->assertSame(
            'monthly',
            $candidate->cadence
        );

        $this->assertSame(
            100.0,
            $candidate->monthlyEquivalent
        );
    }

    public function test_regular_monthly_multi_unit_billing_uses_full_net_period_value(): void
    {
        $client = Client::factory()->create();

        foreach ([
            '2026-05-31',
            '2026-06-30',
            '2026-07-31',
        ] as $date) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'EMAIL-'.$date,
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Monthly Email Licenses Office 365',
                'quantity' => 3,
                'unit_price' => 20,
                'net_amount' => 60,
            ]);
        }

        $candidate = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->firstOrFail(
                fn ($row) => $row->serviceType
                    === 'microsoft365'
            );

        $this->assertSame(
            'monthly',
            $candidate->cadence
        );

        $this->assertSame(
            60.0,
            $candidate->monthlyEquivalent
        );
    }

    public function test_annual_multi_unit_billing_uses_full_net_annual_value(): void
    {
        $client = Client::factory()->create();

        foreach ([
            '2024-11-01',
            '2025-11-01',
            '2026-11-01',
        ] as $date) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'DOMAIN-'.$date,
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Annual Renewal for domains .co.uk & .com',
                'quantity' => 2,
                'unit_price' => 25,
                'net_amount' => 50,
            ]);
        }

        $candidate = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->firstOrFail(
                fn ($row) => $row->serviceType
                    === 'domain'
            );

        $this->assertSame(
            'annual',
            $candidate->cadence
        );

        $this->assertSame(
            4.17,
            $candidate->monthlyEquivalent
        );
    }

    public function test_candidate_aggregation_conserves_invoice_evidence_and_signed_value(): void
    {
        $firstClient = Client::factory()->create();
        $secondClient = Client::factory()->create();

        $firstInvoice = AccountingInvoice::create([
            'client_id' => $firstClient->id,
            'invoice_number' => 'CONSERVE-1',
            'invoice_date' => '2026-07-31',
            'status' => 'paid',
        ]);

        foreach ([
            [
                'description' => 'Monthly Hosting',
                'net' => 75,
            ],
            [
                'description' => 'Monthly Hosting',
                'net' => 75,
            ],
            [
                'description' => 'Advertising Spend',
                'net' => 500,
            ],
            [
                'description' => 'Discount',
                'net' => -100,
            ],
        ] as $row) {
            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $firstInvoice->id,
                'description' => $row['description'],
                'quantity' => 1,
                'unit_price' => $row['net'],
                'net_amount' => $row['net'],
            ]);
        }

        $secondInvoice = AccountingInvoice::create([
            'client_id' => $secondClient->id,
            'invoice_number' => 'CONSERVE-2',
            'invoice_date' => '2026-08-31',
            'status' => 'paid',
        ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $secondInvoice->id,
            'description' => 'Website Design & Development',
            'quantity' => 1,
            'unit_price' => 1000,
            'net_amount' => 1000,
        ]);

        $candidates = app(
            ClientServiceCandidateService::class
        )->all();

        $this->assertSame(
            AccountingInvoiceItem::count(),
            (int) $candidates->sum(
                'evidenceCount'
            )
        );

        $this->assertSame(
            round(
                (float) AccountingInvoiceItem::sum(
                    'net_amount'
                ),
                2
            ),
            round(
                (float) $candidates->sum(
                    'signedObservedNet'
                ),
                2
            )
        );

        $this->assertSame(
            0,
            ClientService::count()
        );
    }

    public function test_period_suffixes_form_one_recurring_candidate_without_merging_old_no_hint_history(): void
    {
        $client = Client::factory()->create();

        /*
         * Historic no-hint evidence remains a separate episode.
         */
        foreach ([
            '2025-01-31',
            '2025-02-28',
            '2025-03-31',
        ] as $date) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'OLD-'.$date,
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Monthly Email Licenses Office 365',
                'quantity' => 1,
                'unit_price' => 60,
                'net_amount' => 60,
            ]);
        }

        foreach ([
            [
                'date' => '2026-01-31',
                'period' => 'Jan26',
            ],
            [
                'date' => '2026-02-28',
                'period' => 'Feb26',
            ],
            [
                'date' => '2026-03-31',
                'period' => 'Mar26',
            ],
        ] as $row) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'NEW-'.$row['date'],
                'invoice_date' => $row['date'],
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Monthly Email Licenses Office 365 - '
                    .$row['period'],
                'quantity' => 1,
                'unit_price' => 60,
                'net_amount' => 60,
            ]);
        }

        $candidates = app(
            ClientServiceCandidateService::class
        )
            ->forClient($client)
            ->filter(
                fn ($candidate) => $candidate->serviceType
                        === 'microsoft365'
            )
            ->values();

        $this->assertCount(
            2,
            $candidates
        );

        $currentEpisode = $candidates
            ->first(
                fn ($candidate) => $candidate->lastObservedOn
                        === '2026-03-31'
            );

        $this->assertNotNull(
            $currentEpisode
        );

        $this->assertNull(
            $currentEpisode->serviceHint
        );

        $this->assertSame(
            3,
            $currentEpisode->evidenceCount
        );

        $this->assertSame(
            'monthly',
            $currentEpisode->cadence
        );

        $this->assertSame(
            60.0,
            $currentEpisode->monthlyEquivalent
        );
    }
}
