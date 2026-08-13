<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverageService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\PaymentIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTruthCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_truth_coverage_reports_known_and_missing_sources(): void
    {
        $client =
            Client::factory()->create([
                'name' => 'Walker',
                'status' => 'active',
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

        PaymentIdentity::create([
            'client_id' => $client->id,

            'identity_type' => 'reference',

            'identity_value' => 'WALKER-001',

            'normalized_value' => 'walker-001',

            'direction' => 'incoming',

            'confidence' => 95,
        ]);

        $coverage =
            app(
                BusinessTruthCoverageService::class
            )->forClient(
                $client
            );

        $this->assertTrue(
            $coverage->hasInvoices
        );

        $this->assertTrue(
            $coverage->hasPaymentIdentity
        );

        $this->assertFalse(
            $coverage->hasWorkLogs
        );

        $this->assertFalse(
            $coverage->hasServices
        );

        $this->assertSame(
            'Walker',
            $coverage->client
        );
    }
}
