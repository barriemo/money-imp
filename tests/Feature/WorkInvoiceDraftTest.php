<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Domains\Billing\Services\WorkInvoiceDraftService;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkInvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_work_creates_one_invoice_draft(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();

        $connection = ExternalConnection::create([
            'name' => 'FreeAgent',
            'provider' => 'freeagent',
            'status' => 'connected',
        ]);

        ExternalRecord::create([
            'external_connection_id' => $connection->id,

            'resource_type' => 'contact',

            'external_id' => 'contact-123',

            'recordable_type' => Client::class,

            'recordable_id' => $client->id,

            'external_reference' => 'https://api.freeagent.com/v2/contacts/contact-123',
        ]);

        WorkLog::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'description' => 'Landing page changes',
            'minutes' => 60,
            'performed_at' => '2026-08-10',
            'billing_hint' => 'billable',
            'commercial_status' => 'invoice',
            'rate_snapshot' => 95,
            'commercial_value' => 95,
        ]);

        WorkLog::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'description' => 'Tracking fixes',
            'minutes' => 30,
            'performed_at' => '2026-08-10',
            'billing_hint' => 'billable',
            'commercial_status' => 'invoice',
            'rate_snapshot' => 95,
            'commercial_value' => 47.50,
        ]);

        $freeAgent = Mockery::mock(
            FreeAgentClient::class
        );

        $freeAgent
            ->shouldReceive('post')
            ->once()
            ->withArgs(
                function (
                    ExternalConnection $connection,
                    string $endpoint,
                    array $payload
                ): bool {
                    return $endpoint === 'invoices'
                        && $payload['invoice']['invoice_items'][0]['price']
                            === 142.5;
                }
            )
            ->andReturn([
                'invoice' => [
                    'url' => 'https://api.freeagent.com/v2/invoices/invoice-123',

                    'reference' => '9999',

                    'status' => 'Draft',

                    'dated_on' => '2026-08-10',

                    'due_on' => '2026-08-17',

                    'currency' => 'GBP',

                    'net_value' => 142.50,

                    'sales_tax_value' => 28.50,

                    'total_value' => 171.00,

                    'due_value' => 171.00,
                ],
            ]);

        $this->app->instance(
            FreeAgentClient::class,
            $freeAgent
        );

        $invoice = app(
            WorkInvoiceDraftService::class
        )->createForClient(
            $client
        );

        $this->assertSame(
            '9999',
            $invoice->invoice_number
        );

        $this->assertSame(
            '171.00',
            $invoice->gross_amount
        );

        $this->assertSame(
            0,
            WorkLog::query()
                ->where('commercial_status', 'invoice')
                ->count()
        );

        $this->assertSame(
            2,
            WorkLog::query()
                ->where('commercial_status', 'invoiced')
                ->where(
                    'accounting_invoice_id',
                    $invoice->id
                )
                ->count()
        );
    }
}
