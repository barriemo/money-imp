<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Evidence\InvoiceTruthEvidenceService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTruthEvidenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_without_bank_evidence_is_flagged_as_conflict(): void
    {
        $client =
            Client::factory()->create();

        $invoice =
            AccountingInvoice::create([
                'client_id' => $client->id,

                'invoice_number' => 'INV-EVIDENCE',

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

        $evidence =
            app(
                InvoiceTruthEvidenceService::class
            )->forInvoice(
                $invoice
            );

        $this->assertTrue(
            $evidence->accountingSaysPaid
        );

        $this->assertFalse(
            $evidence->hasBankEvidence
        );

        $this->assertTrue(
            $evidence->hasEvidenceConflict
        );

        $this->assertSame(
            60,
            $evidence->confidence
        );
    }
}
