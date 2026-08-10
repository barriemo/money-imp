<?php

namespace Tests\Feature;

use App\Domains\Billing\Services\FreeAgentInvoiceSendService;
use App\Models\AccountingInvoice;
use App\Models\BillingReview;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeAgentInvoiceSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_draft_can_be_emailed(): void
    {
        $connection = ExternalConnection::create([
            'provider' => 'freeagent',
            'name' => 'Purple Imp FreeAgent',
            'status' => 'connected',
            'access_token' => 'test',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
        ]);

        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Affertons Limited',
            'status' => 'active',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '2135',
            'status' => 'draft',
            'invoice_date' => '2026-07-30',
            'due_date' => '2026-08-06',
            'gross_amount' => 90,
            'outstanding_amount' => 90,
        ]);

        BillingReview::create([
            'accounting_invoice_id' => $invoice->id,
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'invoice',
            'external_id' => '2135',
            'recordable_type' => AccountingInvoice::class,
            'recordable_id' => $invoice->id,
            'external_reference' => 'https://api.freeagent.com/v2/invoices/2135',
        ]);

        Http::fake([
            'https://api.freeagent.com/v2/invoices/2135/send_email' => Http::response([], 200),
        ]);

        app(FreeAgentInvoiceSendService::class)
            ->send($invoice->load('billingReview'));

        Http::assertSent(function ($request) {
            return
                $request->method() === 'POST'
                && $request->url()
                    === 'https://api.freeagent.com/v2/invoices/2135/send_email'
                && $request['invoice']['email']['use_template'] === true;
        });
    }

    public function test_unapproved_draft_cannot_be_emailed(): void
    {
        $client = Client::create([
            'name' => 'AGM Stone',
            'status' => 'active',
        ]);

        $invoice = AccountingInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => '2137',
            'status' => 'draft',
            'gross_amount' => 90,
            'outstanding_amount' => 90,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Invoice has not been approved in Money Imp.'
        );

        app(FreeAgentInvoiceSendService::class)
            ->send($invoice);
    }
}
