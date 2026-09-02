<?php

namespace Tests\Feature;

use App\Domains\CommercialTruth\Services\ClientServiceReconciliationQueueService;
use App\Domains\CommercialTruth\Services\CompositeCommercialEvidenceReviewQueueService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ClientService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositeCommercialEvidenceReviewQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_composite_evidence_is_visible_only_in_composite_review_queue(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'MML Law',
            ]);

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => '2134',
                'invoice_date' => '2026-07-31',
                'status' => 'paid',
            ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'description' => 'Monthly Consultancy / Implementations / Support (retainer) / Website Development / App Development / SEO / Content .',
            'quantity' => 1,
            'unit_price' => 4000,
            'net_amount' => 4000,
        ]);

        $asOf =
            CarbonImmutable::parse(
                '2026-09-02'
            );

        $composite =
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                $asOf
            );

        $this->assertCount(
            1,
            $composite
        );

        $assessment =
            $composite->first();

        $this->assertSame(
            'composite',
            $assessment
                ->candidate
                ->serviceType
        );

        $this->assertSame(
            'needs_commercial_review',
            $assessment
                ->promotionReadiness
        );

        $this->assertSame(
            [
                'retainer',
                'support',
                'development',
                'seo',
                'content',
            ],
            $assessment
                ->candidate
                ->commercialComponents
        );

        $this->assertNull(
            $assessment
                ->currentMonthlyEquivalent
        );

        $this->assertSame(
            4000.0,
            $assessment
                ->candidate
                ->signedObservedNet
        );

        /*
         * Composite evidence must never enter the ordinary
         * service-existence reconciliation queue.
         */
        $this->assertCount(
            0,
            app(
                ClientServiceReconciliationQueueService::class
            )->ready(
                $asOf
            )
        );
    }

    public function test_attributed_composite_item_does_not_hide_identical_unattributed_source_item(): void
    {
        $client =
            Client::factory()->create();

        $service =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Existing Canonical Service',
                'type' => 'service',
                'status' => 'active',
            ]);

        $description =
            'Consultancy / Support / Website Development / SEO / Content';

        $attributedInvoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'COMP-OLD',
                'invoice_date' => '2026-07-31',
                'status' => 'paid',
            ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $attributedInvoice->id,

            'description' => $description,

            'quantity' => 1,
            'unit_price' => 4000,
            'net_amount' => 4000,

            'client_service_id' => $service->id,
        ]);

        $unattributedInvoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'COMP-NEW',
                'invoice_date' => '2026-08-31',
                'status' => 'paid',
            ]);

        $unattributed =
            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $unattributedInvoice->id,

                'description' => $description,

                'quantity' => 1,
                'unit_price' => 4000,
                'net_amount' => 4000,
            ]);

        $queue =
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            );

        $this->assertCount(
            1,
            $queue
        );

        $candidate =
            $queue
                ->first()
                ->candidate;

        $this->assertSame(
            1,
            $candidate->evidenceCount
        );

        $this->assertSame(
            [
                (string) $unattributed->id,
            ],
            $candidate->invoiceItemIds
        );
    }

    public function test_canonically_attributed_evidence_is_not_shown_as_unresolved_composite_evidence(): void
    {
        $client =
            Client::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => 'COMP-ATTR-1',
                'invoice_date' => '2026-07-31',
                'status' => 'paid',
            ]);

        $service =
            ClientService::create([
                'client_id' => $client->id,
                'name' => 'Existing Canonical Service',
                'type' => 'service',
                'status' => 'active',
            ]);

        AccountingInvoiceItem::create([
            'accounting_invoice_id' => $invoice->id,
            'description' => 'Consultancy / Support / Website Development / SEO / Content',
            'quantity' => 1,
            'unit_price' => 4000,
            'net_amount' => 4000,
            'client_service_id' => $service->id,
        ]);

        $this->assertCount(
            0,
            app(
                CompositeCommercialEvidenceReviewQueueService::class
            )->ready(
                CarbonImmutable::parse(
                    '2026-09-02'
                )
            )
        );
    }
}
