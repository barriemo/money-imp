<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Evidence\ClientPaymentEvidenceSummaryService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPaymentEvidenceSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_payment_evidence_summary_reports_unsupported_paid_invoices(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Evidence Client',
            ]);

        AccountingInvoice::create([
            'client_id' => $client->id,

            'invoice_number' => 'INV-001',

            'status' => 'paid',

            'invoice_date' => now(),

            'due_date' => now(),

            'currency' => 'GBP',

            'net_amount' => 1000,

            'tax_amount' => 200,

            'gross_amount' => 1200,

            'paid_amount' => 1200,

            'outstanding_amount' => 0,
        ]);

        $summary =
            app(
                ClientPaymentEvidenceSummaryService::class
            )->forClient(
                $client
            );

        $this->assertSame(
            1,
            $summary->paidInvoiceCount
        );

        $this->assertSame(
            0,
            $summary->approvedPaymentAllocationCount
        );

        $this->assertSame(
            1,
            $summary->paidInvoicesWithoutApprovedEvidence
        );

        $this->assertSame(
            0,
            $summary->confidence
        );
    }
}
