<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentContactSyncService;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeAgentContactSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_freeagent_clients_are_imported_and_idempotent(): void
    {
        $connection = ExternalConnection::create([
            'provider' => 'freeagent',
            'name' => 'Purple Imp FreeAgent',
            'status' => 'connected',
            'access_token' => 'test-access',
            'refresh_token' => 'test-refresh',
            'token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            'https://api.freeagent.com/v2/contacts*' => Http::response([
                'contacts' => [
                    [
                        'url' => 'https://api.freeagent.com/v2/contacts/123',
                        'organisation_name' => 'Waters Edge',
                        'email' => 'hello@example.com',
                        'status' => 'Active',
                        'account_balance' => '-450.00',
                        'sales_tax_registration_number' => 'GB123456789',
                        'created_at' => '2025-01-01T10:00:00Z',
                        'updated_at' => '2026-08-01T10:00:00Z',
                    ],
                ],
            ]),
        ]);

        $service = app(FreeAgentContactSyncService::class);

        $first = $service->sync($connection);

        $this->assertSame(1, $first->records_created);
        $this->assertSame(1, Client::count());
        $this->assertSame(1, ExternalRecord::count());

        $client = Client::firstOrFail();

        $this->assertSame('Waters Edge', $client->name);
        $this->assertSame('GB123456789', $client->vat_number);

        $second = $service->sync($connection->refresh());

        $this->assertSame(0, $second->records_created);
        $this->assertSame(1, $second->records_updated);

        $this->assertSame(1, Client::count());
        $this->assertSame(1, ExternalRecord::count());

        $this->assertSame(
            $client->id,
            ExternalRecord::firstOrFail()->recordable_id
        );
    }
}
