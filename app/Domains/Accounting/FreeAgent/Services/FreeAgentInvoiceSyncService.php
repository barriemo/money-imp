<?php

namespace App\Domains\Accounting\FreeAgent\Services;

use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\SyncFailure;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class FreeAgentInvoiceSyncService
{
    public function __construct(
        private readonly FreeAgentClient $client,
    ) {}

    public function sync(ExternalConnection $connection): SyncRun
    {
        $run = SyncRun::create([
            'external_connection_id' => $connection->id,
            'resource_type' => 'invoices',
            'direction' => 'inbound',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $page = 1;

            do {
                $response = $this->client->get(
                    $connection,
                    'invoices',
                    [
                        'nested_invoice_items' => 'true',
                        'page' => $page,
                        'per_page' => 100,
                    ]
                );

                $invoices = $response['invoices'] ?? [];

                foreach ($invoices as $invoice) {
                    $run->increment('records_seen');

                    try {
                        $this->syncInvoice(
                            $connection,
                            $invoice,
                            $run
                        );
                    } catch (Throwable $exception) {
                        $run->increment('records_failed');

                        SyncFailure::create([
                            'sync_run_id' => $run->id,
                            'resource_type' => 'invoice',
                            'external_id' => $this->externalId($invoice),
                            'failure_type' => 'invoice_sync_error',
                            'message' => $exception->getMessage(),
                            'payload' => $invoice,
                        ]);
                    }
                }

                $page++;
            } while (count($invoices) === 100);

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

    private function syncInvoice(
        ExternalConnection $connection,
        array $source,
        SyncRun $run
    ): void {
        DB::transaction(function () use (
            $connection,
            $source,
            $run
        ): void {
            $externalId = $this->externalId($source);

            $client = $this->resolveClient(
                $connection,
                (string) ($source['contact'] ?? '')
            );

            $externalRecord = ExternalRecord::query()
                ->where('external_connection_id', $connection->id)
                ->where('resource_type', 'invoice')
                ->where('external_id', $externalId)
                ->first();

            $attributes = [
                'client_id' => $client?->id,
                'invoice_number' => $source['reference'] ?? null,
                'status' => $this->normaliseStatus(
                    (string) ($source['status'] ?? 'Draft')
                ),
                'invoice_date' => $source['dated_on'] ?? null,
                'due_date' => $source['due_on'] ?? null,
                'currency' => $source['currency'] ?? 'GBP',
                'net_amount' => $source['net_value'] ?? 0,
                'tax_amount' => $source['sales_tax_value'] ?? 0,
                'gross_amount' => $source['total_value'] ?? 0,
                'paid_amount' => $source['paid_value'] ?? 0,
                'outstanding_amount' => $source['due_value'] ?? 0,
                'notes' => $source['comments'] ?? null,
                'metadata' => [
                    'long_status' => $source['long_status'] ?? null,
                    'contact_url' => $source['contact'] ?? null,
                    'project_url' => $source['project'] ?? null,
                    'payment_terms_in_days' => $source['payment_terms_in_days'] ?? null,
                ],
            ];

            if ($externalRecord?->recordable instanceof AccountingInvoice) {
                $invoice = $externalRecord->recordable;
                $invoice->update($attributes);

                $run->increment('records_updated');
            } else {
                $invoice = AccountingInvoice::create($attributes);

                $run->increment('records_created');
            }

            $invoice->items()->delete();

            foreach ($source['invoice_items'] ?? [] as $item) {
                AccountingInvoiceItem::create([
                    'accounting_invoice_id' => $invoice->id,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 0,
                    'unit_price' => $item['price'] ?? 0,
                    'net_amount' => $this->itemNetAmount($item),
                    'tax_rate' => $item['sales_tax_rate'] ?? null,
                    'tax_amount' => $this->itemTaxAmount($item),
                    'gross_amount' => $this->itemGrossAmount($item),
                    'metadata' => [
                        'freeagent_item_type' => $item['item_type'] ?? null,
                        'freeagent_item_url' => $item['url'] ?? null,
                        'position' => $item['position'] ?? null,
                    ],
                ]);
            }

            ExternalRecord::updateOrCreate(
                [
                    'external_connection_id' => $connection->id,
                    'resource_type' => 'invoice',
                    'external_id' => $externalId,
                ],
                [
                    'recordable_type' => AccountingInvoice::class,
                    'recordable_id' => $invoice->id,
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
        });
    }

    private function resolveClient(
        ExternalConnection $connection,
        string $contactUrl
    ): ?Client {
        if ($contactUrl === '') {
            return null;
        }

        $contactId = basename(
            parse_url($contactUrl, PHP_URL_PATH)
        );

        $record = ExternalRecord::query()
            ->where('external_connection_id', $connection->id)
            ->where('resource_type', 'contact')
            ->where('external_id', $contactId)
            ->first();

        return $record?->recordable instanceof Client
            ? $record->recordable
            : null;
    }

    private function externalId(array $invoice): string
    {
        $url = (string) ($invoice['url'] ?? '');

        if ($url === '') {
            throw new RuntimeException(
                'FreeAgent invoice is missing its URL.'
            );
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function normaliseStatus(string $status): string
    {
        return match (strtolower($status)) {
            'draft' => 'draft',
            'scheduled to email' => 'scheduled',
            'open' => 'outstanding',
            'overdue' => 'overdue',
            'paid' => 'paid',
            'overpaid' => 'overpaid',
            'refunded' => 'refunded',
            'written-off' => 'written_off',
            'part written-off' => 'part_written_off',
            'zero value' => 'zero_value',
            default => strtolower(
                str_replace([' ', '-'], '_', $status)
            ),
        };
    }

    private function itemNetAmount(array $item): float
    {
        return (float) ($item['price'] ?? 0)
            * (float) ($item['quantity'] ?? 0);
    }

    private function itemTaxAmount(array $item): float
    {
        $net = $this->itemNetAmount($item);
        $rate = (float) ($item['sales_tax_rate'] ?? 0);

        return round($net * ($rate / 100), 2);
    }

    private function itemGrossAmount(array $item): float
    {
        return round(
            $this->itemNetAmount($item)
            + $this->itemTaxAmount($item),
            2
        );
    }
}
