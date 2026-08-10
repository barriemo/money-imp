<?php

namespace Tests\Feature;

use App\Domains\Billing\Services\FreeAgentDraftInvoiceService;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeAgentDraftInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_previous_invoice_can_be_duplicated_as_july_draft(): void
    {
        $connection = ExternalConnection::create([
            'provider' => 'freeagent',
            'name' => 'Purple Imp FreeAgent',
            'status' => 'connected',
            'access_token' => 'test',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
        ]);

        $client = Client::create([
            'name' => 'Affertons Limited',
            'status' => 'active',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '2000',
            'status' => 'paid',
            'invoice_date' => '2026-06-29',
            'due_date' => '2026-07-06',
            'gross_amount' => 90,
            'outstanding_amount' => 0,
        ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'invoice',
            'external_id' => '2000',
            'recordable_type' => AccountingInvoice::class,
            'recordable_id' => $invoice->id,
            'external_reference' => 'https://api.freeagent.com/v2/invoices/2000',
        ]);

        Http::fake([
            'https://api.freeagent.com/v2/invoices/2000/duplicate' => Http::response([
                'invoice' => [
                    'url' => 'https://api.freeagent.com/v2/invoices/3000',
                    'reference' => '2132',
                    'status' => 'Draft',
                ],
            ]),

            'https://api.freeagent.com/v2/invoices/3000' => Http::response([
                'invoice' => [
                    'url' => 'https://api.freeagent.com/v2/invoices/3000',
                    'reference' => '2132',
                    'status' => 'Draft',
                    'dated_on' => '2026-07-29',
                    'due_on' => '2026-08-05',
                ],
            ]),
        ]);

        $result = app(FreeAgentDraftInvoiceService::class)
            ->createMonthlyDraft(
                $client,
                CarbonImmutable::create(2026, 7, 1)
            );

        $this->assertSame('Draft', $result['status']);
        $this->assertSame('2026-07-29', $result['dated_on']);
        $this->assertSame('2026-08-05', $result['due_on']);

        Http::assertSentCount(2);
    }
}
