<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Domains\Accounting\FreeAgent\Evidence\FreeAgentVatEvidence;
use App\Models\ExternalConnection;
use Illuminate\Support\Collection;

final class FreeAgentVatEvidenceService
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    /**
     * @return Collection<int, FreeAgentVatEvidence>
     */
    public function current(): Collection
    {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            return collect();
        }

        $response = $this->client->get(
            $connection,
            '/vat_returns'
        );

        return collect($response['vat_returns'] ?? [])
            ->flatMap(
                function (array $return): Collection {
                    return collect($return['payments'] ?? [])
                        ->map(
                            fn (array $payment) => new FreeAgentVatEvidence(
                                periodEnd: (string) $return['period_ends_on'],
                                label: (string) ($payment['label'] ?? 'unknown'),
                                amountDue: (float) ($payment['amount_due'] ?? 0),
                                dueDate: (string) ($payment['due_on'] ?? ''),
                                status: (string) ($payment['status'] ?? 'unknown'),
                                filingStatus: (string) ($return['filing_status'] ?? 'unknown'),
                            )
                        );
                }
            );
    }
}
