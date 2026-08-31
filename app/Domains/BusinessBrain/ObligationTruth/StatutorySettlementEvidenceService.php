<?php

namespace App\Domains\BusinessBrain\ObligationTruth;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Models\ExternalConnection;

final class StatutorySettlementEvidenceService implements StatutorySettlementEvidenceProvider
{
    private const CATEGORY_MAP = [
        '817' => 'vat',
        '405' => 'paye_ni',
        '820' => 'corporation_tax',
    ];

    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    public function assess(): StatutorySettlementEvidence
    {
        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->first();

        if (! $connection) {
            return new StatutorySettlementEvidence([], 0, false);
        }

        $transactions = $this->client->get(
            $connection,
            '/bank_transaction_explanations',
            []
        );

        $categories = [];

        foreach (($transactions['bank_transaction_explanations'] ?? []) as $transaction) {
            $category = basename($transaction['category'] ?? '');

            if (! isset(self::CATEGORY_MAP[$category])) {
                continue;
            }

            $type = self::CATEGORY_MAP[$category];

            $amount = abs((float) ($transaction['transfer_value'] ?? 0));

            $categories[$type] ??= [
                'payments_observed' => true,
                'amount' => 0,
                'transactions' => 0,
            ];

            $categories[$type]['amount'] += $amount;
            $categories[$type]['transactions']++;
        }

        $total = array_sum(
            array_column($categories, 'amount')
        );

        return new StatutorySettlementEvidence(
            $categories,
            $total,
            count($categories) > 0
        );
    }
}
