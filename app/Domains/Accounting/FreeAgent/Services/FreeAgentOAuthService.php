<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Models\ExternalConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FreeAgentOAuthService
{
    public function authorizationUrl(): string
    {
        $state = Str::random(40);

        session([
            'freeagent_oauth_state' => $state,
        ]);

        return 'https://api.freeagent.com/v2/approve_app?'.http_build_query([
            'client_id' => config('services.freeagent.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.freeagent.redirect_uri'),
            'state' => $state,
        ]);
    }

    public function connect(string $code): ExternalConnection
    {
        $response = Http::withBasicAuth(
            (string) config('services.freeagent.client_id'),
            (string) config('services.freeagent.client_secret'),
        )
            ->asForm()
            ->post('https://api.freeagent.com/v2/token_endpoint', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => config('services.freeagent.redirect_uri'),
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'FreeAgent token exchange failed: '.$response->body()
            );
        }

        $data = $response->json();

        return ExternalConnection::updateOrCreate(
            [
                'provider' => 'freeagent',
                'name' => 'Purple Imp FreeAgent',
            ],
            [
                'status' => 'connected',
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'token_expires_at' => now()->addSeconds(
                    (int) ($data['expires_in'] ?? 3600)
                ),
                'last_connected_at' => now(),
            ]
        );
    }

    public function refresh(ExternalConnection $connection): ExternalConnection
    {
        $response = Http::withBasicAuth(
            (string) config('services.freeagent.client_id'),
            (string) config('services.freeagent.client_secret'),
        )
            ->asForm()
            ->post('https://api.freeagent.com/v2/token_endpoint', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $connection->refresh_token,
            ]);

        if ($response->failed()) {
            $connection->update([
                'status' => 'error',
                'last_failed_at' => now(),
                'last_error' => $response->body(),
            ]);

            throw new RuntimeException(
                'FreeAgent token refresh failed: '.$response->body()
            );
        }

        $data = $response->json();

        $connection->update([
            'status' => 'connected',
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'token_expires_at' => now()->addSeconds(
                (int) ($data['expires_in'] ?? 3600)
            ),
            'last_failed_at' => null,
            'last_error' => null,
        ]);

        return $connection->refresh();
    }
}
