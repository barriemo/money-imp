<?php

namespace App\Domains\Billing\Services;

use App\Models\Client;
use Carbon\CarbonImmutable;
use Throwable;

class BulkDraftInvoiceService
{
    public function __construct(
        private readonly FreeAgentDraftInvoiceService $drafts,
    ) {}

    public function create(
        array $clientIds,
        CarbonImmutable $month
    ): array {
        $result = [
            'requested' => count($clientIds),
            'created' => [],
            'failed' => [],
        ];

        foreach ($clientIds as $clientId) {
            $client = Client::find($clientId);

            if (! $client) {
                $result['failed'][] = [
                    'client_id' => $clientId,
                    'client' => 'Unknown client',
                    'error' => 'Client not found.',
                ];

                continue;
            }

            try {
                $invoice = $this->drafts->createMonthlyDraft(
                    $client,
                    $month
                );

                $result['created'][] = [
                    'client_id' => $client->id,
                    'client' => $client->name,
                    'reference' => $invoice['reference'] ?? null,
                ];
            } catch (Throwable $exception) {
                $result['failed'][] = [
                    'client_id' => $client->id,
                    'client' => $client->name,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }
}
