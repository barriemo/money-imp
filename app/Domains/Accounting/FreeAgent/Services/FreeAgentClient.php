<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Models\ExternalConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FreeAgentClient
{
    public function __construct(
        private readonly FreeAgentOAuthService $oauth,
    ) {}

    public function get(
        ExternalConnection $connection,
        string $path,
        array $query = []
    ): array {
        $connection = $this->validConnection($connection);

        return $this->request($connection)
            ->get($this->url($path), $query)
            ->throw()
            ->json();
    }

    public function post(
        ExternalConnection $connection,
        string $path,
        array $payload = []
    ): array {
        $connection = $this->validConnection($connection);

        return $this->request($connection)
            ->post($this->url($path), $payload)
            ->throw()
            ->json();
    }

    public function put(
        ExternalConnection $connection,
        string $path,
        array $payload = []
    ): array {
        $connection = $this->validConnection($connection);

        return $this->request($connection)
            ->put($this->url($path), $payload)
            ->throw()
            ->json();
    }

    private function validConnection(
        ExternalConnection $connection
    ): ExternalConnection {
        if (
            $connection->token_expires_at !== null
            && $connection->token_expires_at->isPast()
        ) {
            return $this->oauth->refresh($connection);
        }

        return $connection;
    }

    private function request(ExternalConnection $connection): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($connection->access_token)
            ->withUserAgent('Money Imp / Purple Imp');
    }

    private function url(string $path): string
    {
        return rtrim(
            (string) config('services.freeagent.base_url'),
            '/'
        ).'/'.ltrim($path, '/');
    }
}
