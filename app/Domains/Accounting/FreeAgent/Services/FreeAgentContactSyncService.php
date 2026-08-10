<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\SyncFailure;
use App\Models\SyncRun;
use Throwable;

class FreeAgentContactSyncService
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    public function sync(ExternalConnection $connection): SyncRun
    {
        $run = SyncRun::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'contacts',
            'direction' => 'inbound',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $page = 1;

            do {
                $response = $this->client->get(
                    $connection,
                    'contacts',
                    [
                        'view' => 'clients',
                        'page' => $page,
                        'per_page' => 100,
                    ]
                );

                $contacts = $response['contacts'] ?? [];

                foreach ($contacts as $contact) {
                    $run->increment('records_seen');

                    try {
                        $this->syncContact(
                            $connection,
                            $contact,
                            $run
                        );
                    } catch (Throwable $exception) {
                        $run->increment('records_failed');

                        SyncFailure::create([
                            'sync_run_id' => $run->id,
                            'resource_type' => 'contact',
                            'external_id' => $this->externalId($contact),
                            'failure_type' => 'contact_sync_error',
                            'message' => $exception->getMessage(),
                            'payload' => $contact,
                        ]);
                    }
                }

                $page++;
            } while (count($contacts) === 100);

            $run->update([
                'status' => $run->records_failed > 0
                    ? 'completed_with_errors'
                    : 'completed',
                'finished_at' => now(),
            ]);

            $connection->update([
                'last_synced_at' => now(),
                'last_failed_at' => null,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $connection->update([
                'last_failed_at' => now(),
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $run->refresh();
    }

    private function syncContact(
        ExternalConnection $connection,
        array $contact,
        SyncRun $run
    ): void {
        $externalId = $this->externalId($contact);

        $externalRecord = ExternalRecord::query()
            ->where('external_connection_id', $connection->id)
            ->where('resource_type', 'contact')
            ->where('external_id', $externalId)
            ->first();

        $attributes = [
            'name' => $this->displayName($contact),
            'legal_name' => $contact['organisation_name'] ?? null,
            'email' => $contact['email']
                ?? $contact['billing_email']
                ?? null,
            'phone' => $contact['phone_number']
                ?? $contact['mobile']
                ?? null,
            'status' => strtolower(
                (string) ($contact['status'] ?? 'active')
            ) === 'active' ? 'active' : 'inactive',
            'currency' => 'GBP',
            'vat_number' => $contact['sales_tax_registration_number']
                ?? null,
            'metadata' => [
                'freeagent_account_balance' => $contact['account_balance']
                    ?? null,
                'billing_email' => $contact['billing_email']
                    ?? null,
                'default_payment_terms_in_days' => $contact['default_payment_terms_in_days'] ?? null,
                'town' => $contact['town'] ?? null,
                'postcode' => $contact['postcode'] ?? null,
                'country' => $contact['country'] ?? null,
            ],
        ];

        if ($externalRecord?->recordable instanceof Client) {
            $client = $externalRecord->recordable;

            $client->update($attributes);

            $run->increment('records_updated');
        } else {
            $client = Client::create($attributes);

            $run->increment('records_created');
        }

        ExternalRecord::updateOrCreate(
            [
                'external_connection_id' => $connection->id,
                'resource_type' => 'contact',
                'external_id' => $externalId,
            ],
            [
                'recordable_type' => Client::class,
                'recordable_id' => $client->id,
                'external_reference' => $contact['url'] ?? null,
                'status' => $contact['status'] ?? null,
                'external_created_at' => $contact['created_at'] ?? null,
                'external_updated_at' => $contact['updated_at'] ?? null,
                'last_synced_at' => now(),
                'source_hash' => hash(
                    'sha256',
                    json_encode($contact, JSON_THROW_ON_ERROR)
                ),
                'payload' => $contact,
            ]
        );
    }

    private function externalId(array $contact): string
    {
        $url = (string) ($contact['url'] ?? '');

        if ($url === '') {
            throw new \RuntimeException(
                'FreeAgent contact is missing its URL.'
            );
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function displayName(array $contact): string
    {
        if (! empty($contact['organisation_name'])) {
            return trim((string) $contact['organisation_name']);
        }

        $name = trim(implode(' ', array_filter([
            $contact['first_name'] ?? null,
            $contact['last_name'] ?? null,
        ])));

        if ($name !== '') {
            return $name;
        }

        return 'FreeAgent Client '.$this->externalId($contact);
    }
}
