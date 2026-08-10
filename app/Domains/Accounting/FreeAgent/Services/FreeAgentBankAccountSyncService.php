<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Models\BankAccount;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\SyncRun;
use RuntimeException;
use Throwable;

class FreeAgentBankAccountSyncService
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    public function sync(ExternalConnection $connection): SyncRun
    {
        $run = SyncRun::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'bank_accounts',
            'direction' => 'inbound',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $response = $this->client->get(
                $connection,
                'bank_accounts'
            );

            foreach ($response['bank_accounts'] ?? [] as $source) {
                $run->increment('records_seen');

                $externalId = $this->externalId($source);

                $record = ExternalRecord::query()
                    ->where('external_connection_id', $connection->id)
                    ->where('resource_type', 'bank_account')
                    ->where('external_id', $externalId)
                    ->first();

                $attributes = [
                    'name' => $source['name'] ?? 'FreeAgent Bank Account',
                    'bank_name' => $source['bank_name'] ?? null,
                    'currency' => $source['currency'] ?? 'GBP',
                    'account_type' => $source['type'] ?? null,
                    'account_identifier' => $source['iban']
                        ?? $source['email']
                        ?? null,
                    'account_last_four' => isset($source['account_number'])
                        ? substr((string) $source['account_number'], -4)
                        : null,
                    'status' => $source['status'] ?? 'active',
                    'current_balance' => $source['current_balance'] ?? null,
                    'balance_at' => $source['updated_at'] ?? now(),
                    'metadata' => [
                        'opening_balance' => $source['opening_balance'] ?? null,
                        'is_personal' => $source['is_personal'] ?? null,
                        'bank_guess_enabled' => $source['bank_guess_enabled'] ?? null,
                    ],
                ];

                if ($record?->recordable instanceof BankAccount) {
                    $account = $record->recordable;
                    $account->update($attributes);
                    $run->increment('records_updated');
                } else {
                    $account = BankAccount::create($attributes);
                    $run->increment('records_created');
                }

                ExternalRecord::updateOrCreate(
                    [
                        'external_connection_id' => $connection->id,
                        'resource_type' => 'bank_account',
                        'external_id' => $externalId,
                    ],
                    [
                        'recordable_type' => BankAccount::class,
                        'recordable_id' => $account->id,
                        'external_reference' => $source['url'] ?? null,
                        'status' => $source['status'] ?? null,
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

            $run->update([
                'status' => 'completed',
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

    private function externalId(array $source): string
    {
        $url = (string) ($source['url'] ?? '');

        if ($url === '') {
            throw new RuntimeException(
                'FreeAgent bank account is missing its URL.'
            );
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }
}
