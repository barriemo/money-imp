<?php

namespace Tests\Feature;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentOAuthService;
use App\Models\ExternalConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FreeAgentOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_exchange_creates_encrypted_connection(): void
    {
        config([
            'services.freeagent.client_id' => 'test-client',
            'services.freeagent.client_secret' => 'test-secret',
            'services.freeagent.redirect_uri' => 'http://localhost/callback',
        ]);

        Http::fake([
            'https://api.freeagent.com/v2/token_endpoint' => Http::response([
                'access_token' => 'secret-access-token',
                'refresh_token' => 'secret-refresh-token',
                'expires_in' => 3600,
            ]),
        ]);

        $connection = app(FreeAgentOAuthService::class)
            ->connect('authorization-code');

        $this->assertSame('freeagent', $connection->provider);
        $this->assertSame('connected', $connection->status);
        $this->assertSame('secret-access-token', $connection->access_token);
        $this->assertSame('secret-refresh-token', $connection->refresh_token);

        $raw = \DB::table('external_connections')
            ->where('id', $connection->id)
            ->first();

        $this->assertNotSame(
            'secret-access-token',
            $raw->access_token
        );

        $this->assertNotSame(
            'secret-refresh-token',
            $raw->refresh_token
        );
    }

    public function test_expired_token_can_be_refreshed(): void
    {
        config([
            'services.freeagent.client_id' => 'test-client',
            'services.freeagent.client_secret' => 'test-secret',
        ]);

        $connection = ExternalConnection::create([
            'provider' => 'freeagent',
            'name' => 'Purple Imp FreeAgent',
            'status' => 'connected',
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'token_expires_at' => now()->subMinute(),
        ]);

        Http::fake([
            'https://api.freeagent.com/v2/token_endpoint' => Http::response([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh',
                'expires_in' => 3600,
            ]),
        ]);

        $connection = app(FreeAgentOAuthService::class)
            ->refresh($connection);

        $this->assertSame('new-access', $connection->access_token);
        $this->assertSame('new-refresh', $connection->refresh_token);
        $this->assertTrue($connection->token_expires_at->isFuture());
    }
}
