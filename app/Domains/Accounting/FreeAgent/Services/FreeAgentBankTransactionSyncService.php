<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankTransactionExplanation;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\SyncFailure;
use App\Models\SyncRun;
use RuntimeException;
use Throwable;

class FreeAgentBankTransactionSyncService
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    public function sync(ExternalConnection $connection): SyncRun
    {
        $run = SyncRun::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'bank_transactions',
            'direction' => 'inbound',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $accounts = ExternalRecord::query()
                ->where('external_connection_id', $connection->id)
                ->where('resource_type', 'bank_account')
                ->with('recordable')
                ->get();

            foreach ($accounts as $accountRecord) {
                if (! $accountRecord->recordable instanceof BankAccount) {
                    continue;
                }

                $this->syncAccount(
                    $connection,
                    $accountRecord->recordable,
                    (string) $accountRecord->external_reference,
                    $run
                );
            }

            $run->update([
                'status' => $run->records_failed > 0
                    ? 'completed_with_errors'
                    : 'completed',
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $run->refresh();
    }

    private function syncAccount(
        ExternalConnection $connection,
        BankAccount $account,
        string $accountUrl,
        SyncRun $run
    ): void {
        if ($accountUrl === '') {
            throw new RuntimeException(
                'Money Imp bank account is missing its FreeAgent URL.'
            );
        }

        $page = 1;

        do {
            $response = $this->client->get(
                $connection,
                'bank_transactions',
                [
                    'bank_account' => $accountUrl,
                    'page' => $page,
                    'per_page' => 100,
                ]
            );

            $transactions = $response['bank_transactions'] ?? [];

            foreach ($transactions as $source) {
                $run->increment('records_seen');

                try {
                    $this->syncTransaction(
                        $connection,
                        $account,
                        $source,
                        $run
                    );
                } catch (Throwable $exception) {
                    $run->increment('records_failed');

                    SyncFailure::create([
                        'sync_run_id' => $run->id,
                        'resource_type' => 'bank_transaction',
                        'external_id' => $this->externalId($source),
                        'failure_type' => 'bank_transaction_sync_error',
                        'message' => $exception->getMessage(),
                        'payload' => $source,
                    ]);
                }
            }

            $page++;
        } while (count($transactions) === 100);
    }

    private function syncTransaction(
        ExternalConnection $connection,
        BankAccount $account,
        array $source,
        SyncRun $run
    ): void {
        $externalId = $this->externalId($source);

        $record = ExternalRecord::query()
            ->where('external_connection_id', $connection->id)
            ->where('resource_type', 'bank_transaction')
            ->where('external_id', $externalId)
            ->first();

        $transactionHash = hash(
            'sha256',
            implode('|', [
                $account->id,
                $source['transaction_id'] ?? $externalId,
                $source['dated_on'] ?? '',
                $source['amount'] ?? '',
                $source['description'] ?? '',
            ])
        );

        $unexplainedAmount = (float) ($source['unexplained_amount'] ?? 0);

        $attributes = [
            'bank_account_id' => $account->id,
            'transaction_date' => $source['dated_on'],
            'amount' => $source['amount'] ?? 0,
            'currency' => $account->currency,
            'description' => $source['description'] ?? null,
            'reference' => $source['transaction_id'] ?? null,
            'counterparty_name' => null,
            'counterparty_account' => null,
            'transaction_type' => ! empty($source['is_manual'])
                ? 'manual'
                : 'imported',
            'match_status' => abs($unexplainedAmount) > 0.00001
                ? 'unmatched'
                : 'reconciled',
            'source_type' => 'freeagent',
            'transaction_hash' => $transactionHash,
            'raw_payload' => $source,
            'metadata' => [
                'freeagent_unexplained_amount' => $source['unexplained_amount'] ?? null,
                'freeagent_full_description' => $source['full_description'] ?? null,
                'freeagent_uploaded_at' => $source['uploaded_at'] ?? null,
                'freeagent_is_manual' => $source['is_manual'] ?? null,
                'freeagent_matching_transactions_count' => $source['matching_transactions_count'] ?? null,
            ],
        ];

        if ($record?->recordable instanceof BankTransaction) {
            $transaction = $record->recordable;
            $transaction->update($attributes);

            $run->increment('records_updated');
        } else {
            $existing = BankTransaction::query()
                ->where('bank_account_id', $account->id)
                ->where('transaction_hash', $transactionHash)
                ->first();

            if ($existing) {
                $transaction = $existing;
                $transaction->update($attributes);

                $run->increment('records_updated');
            } else {
                $transaction = BankTransaction::create($attributes);

                $run->increment('records_created');
            }
        }

        $this->syncExplanations(
            $transaction,
            $source['bank_transaction_explanations'] ?? []
        );

        ExternalRecord::updateOrCreate(
            [
                'external_connection_id' => $connection->id,
                'resource_type' => 'bank_transaction',
                'external_id' => $externalId,
            ],
            [
                'recordable_type' => BankTransaction::class,
                'recordable_id' => $transaction->id,
                'external_reference' => $source['url'] ?? null,
                'external_created_at' => $source['created_at'] ?? null,
                'external_updated_at' => $source['updated_at'] ?? null,
                'last_synced_at' => now(),
                'source_hash' => hash(
                    'sha256',
                    json_encode($source, JSON_THROW_ON_ERROR)
                ),
                'payload' => $source,
            ]
        );
    }

    private function syncExplanations(
        BankTransaction $transaction,
        array $explanations
    ): void {
        $transaction->explanations()->delete();

        foreach ($explanations as $source) {
            BankTransactionExplanation::create([
                'bank_transaction_id' => $transaction->id,
                'type' => $source['type']
                    ?? $source['entry_type']
                    ?? null,
                'category' => $source['category'] ?? null,
                'amount' => $source['gross_value'] ?? 0,
                'description' => $source['description'] ?? null,
                'status' => 'observed',
                'metadata' => [
                    'freeagent_url' => $source['url'] ?? null,
                    'freeagent_entry_type' => $source['entry_type'] ?? null,
                    'freeagent_is_deletable' => $source['is_deletable'] ?? null,
                ],
            ]);
        }
    }

    private function externalId(array $source): string
    {
        $url = (string) ($source['url'] ?? '');

        if ($url === '') {
            throw new RuntimeException(
                'FreeAgent bank transaction is missing its URL.'
            );
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }
}
