<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Domains\Accounting\Contracts\BankAccountEvidenceProvider;
use App\Domains\Accounting\FreeAgent\Evidence\FreeAgentBankAccountEvidence;
use App\Models\ExternalConnection;
use Illuminate\Support\Collection;

final class FreeAgentBankAccountEvidenceService implements BankAccountEvidenceProvider
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    /**
     * @return Collection<int, FreeAgentBankAccountEvidence>
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
            'bank_accounts',
            [
                'per_page' => 100,
            ]
        );

        return collect($response['bank_accounts'] ?? [])
            ->map(
                fn (array $account) => new FreeAgentBankAccountEvidence(
                    accountId: basename($account['url']),
                    name: $account['name'],
                    type: $account['type'],
                    balance: (float) $account['current_balance'],
                    transactionCount: (int) $account['total_count'],
                    unexplainedTransactionCount: (int) $account['unexplained_transaction_count'],
                    markedForReviewCount: (int) $account['marked_for_review_count'],
                    manualTransactionCount: (int) $account['manually_added_transaction_count'],
                    categoryGroups: $account['marked_for_review_category_group_counts'] ?? [],
                    latestActivityDate: $account['latest_activity_date'] ?? null,
                    bankFeedEnabled: array_key_exists(
                        'bank_feed_enabled',
                        $account
                    ) && $account['bank_feed_enabled'] !== null
                        ? (bool) $account['bank_feed_enabled']
                        : null,
                )
            );
    }
}
