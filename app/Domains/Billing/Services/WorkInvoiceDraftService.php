<?php

namespace App\Domains\Billing\Services;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Models\AccountingInvoice;
use App\Models\Client;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use App\Models\WorkLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkInvoiceDraftService
{
    public function __construct(
        private readonly FreeAgentClient $freeAgent,
    ) {}

    public function createForClient(
        Client $client
    ): AccountingInvoice {
        $logs = WorkLog::query()
            ->where('client_id', $client->id)
            ->where('commercial_status', 'invoice')
            ->whereNull('accounting_invoice_id')
            ->orderBy('performed_at')
            ->get();

        if ($logs->isEmpty()) {
            throw new RuntimeException(
                'There is no uninvoiced approved work for this client.'
            );
        }

        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->firstOrFail();

        $contact = ExternalRecord::query()
            ->where(
                'external_connection_id',
                $connection->id
            )
            ->where('resource_type', 'contact')
            ->where('recordable_type', Client::class)
            ->where('recordable_id', $client->id)
            ->first();

        if (! $contact?->external_reference) {
            throw new RuntimeException(
                'Client has no FreeAgent contact mapping.'
            );
        }

        $totalMinutes = (int) $logs->sum('minutes');

        $totalValue = round(
            $logs->sum(
                fn (WorkLog $log) => (float) $log->commercial_value
            ),
            2
        );

        $datedOn = now()->toDateString();

        $response = $this->freeAgent->post(
            $connection,
            'invoices',
            [
                'invoice' => [
                    'contact' => $contact->external_reference,

                    'dated_on' => $datedOn,

                    'payment_terms_in_days' => 7,

                    'comments' => 'Additional work recorded and approved in Money Imp.',

                    'invoice_items' => [
                        [
                            'description' => $this->description(
                                $logs,
                                $totalMinutes
                            ),

                            'item_type' => 'Services',

                            'quantity' => 1,

                            'price' => $totalValue,

                            'sales_tax_rate' => 20,
                        ],
                    ],
                ],
            ]
        );

        $draft = $response['invoice'] ?? null;

        if (
            ! is_array($draft)
            || empty($draft['url'])
        ) {
            throw new RuntimeException(
                'FreeAgent did not return the created invoice.'
            );
        }

        return DB::transaction(
            function () use (
                $client,
                $connection,
                $draft,
                $logs,
                $datedOn,
                $totalValue
            ): AccountingInvoice {
                $externalId = basename(
                    parse_url(
                        $draft['url'],
                        PHP_URL_PATH
                    )
                );

                $invoice = AccountingInvoice::create([
                    'client_id' => $client->id,

                    'invoice_number' => $draft['reference'] ?? null,

                    'status' => 'draft',

                    'invoice_date' => $draft['dated_on']
                            ?? $datedOn,

                    'due_date' => $draft['due_on'] ?? null,

                    'currency' => $draft['currency'] ?? 'GBP',

                    'net_amount' => $draft['net_value']
                            ?? $totalValue,

                    'tax_amount' => $draft['sales_tax_value']
                            ?? round(
                                $totalValue * 0.20,
                                2
                            ),

                    'gross_amount' => $draft['total_value']
                            ?? round(
                                $totalValue * 1.20,
                                2
                            ),

                    'paid_amount' => 0,

                    'outstanding_amount' => $draft['due_value']
                            ?? round(
                                $totalValue * 1.20,
                                2
                            ),

                    'notes' => 'Generated from approved Money Imp work logs.',
                ]);

                ExternalRecord::create([
                    'external_connection_id' => $connection->id,

                    'resource_type' => 'invoice',

                    'external_id' => $externalId,

                    'recordable_type' => AccountingInvoice::class,

                    'recordable_id' => $invoice->id,

                    'external_reference' => $draft['url'],

                    'status' => $draft['status'] ?? 'Draft',

                    'last_synced_at' => now(),

                    'payload' => $draft,
                ]);

                WorkLog::query()
                    ->whereIn(
                        'id',
                        $logs->pluck('id')
                    )
                    ->update([
                        'commercial_status' => 'invoiced',

                        'accounting_invoice_id' => $invoice->id,
                    ]);

                return $invoice;
            }
        );
    }

    private function description(
        Collection $logs,
        int $totalMinutes
    ): string {
        $hours = floor(
            $totalMinutes / 60
        );

        $minutes = $totalMinutes % 60;

        $time = match (true) {
            $hours > 0 && $minutes > 0 => "{$hours}h {$minutes}m",

            $hours > 0 => "{$hours}h",

            default => "{$minutes}m",
        };

        $details = $logs
            ->pluck('description')
            ->filter()
            ->unique()
            ->take(6)
            ->implode('; ');

        return 'Additional website & digital support'
            .' — '.$time
            .($details !== ''
                ? ' — '.$details
                : '');
    }
}
