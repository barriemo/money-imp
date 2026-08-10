<?php

namespace App\Domains\Billing\Services;

use App\Domains\Accounting\FreeAgent\Services\FreeAgentClient;
use App\Models\AccountingInvoice;
use App\Models\ExternalConnection;
use App\Models\ExternalRecord;
use RuntimeException;

class FreeAgentInvoiceSendService
{
    public function __construct(
        private readonly FreeAgentClient $freeAgent,
    ) {}

    public function send(AccountingInvoice $invoice): array
    {
        if ($invoice->status !== 'draft') {
            throw new RuntimeException(
                'Only FreeAgent draft invoices can be sent.'
            );
        }

        if ($invoice->billingReview?->status !== 'approved') {
            throw new RuntimeException(
                'Invoice has not been approved in Money Imp.'
            );
        }

        $connection = ExternalConnection::query()
            ->where('provider', 'freeagent')
            ->where('status', 'connected')
            ->firstOrFail();

        $record = ExternalRecord::query()
            ->where('external_connection_id', $connection->id)
            ->where('resource_type', 'invoice')
            ->where('recordable_type', AccountingInvoice::class)
            ->where('recordable_id', $invoice->id)
            ->first();

        if (! $record?->external_reference) {
            throw new RuntimeException(
                'Invoice has no FreeAgent mapping.'
            );
        }

        $externalId = basename(
            parse_url(
                $record->external_reference,
                PHP_URL_PATH
            )
        );

        return $this->freeAgent->post(
            $connection,
            'invoices/'.$externalId.'/send_email',
            [
                'invoice' => [
                    'email' => [
                        'use_template' => true,
                    ],
                ],
            ]
        );
    }
}
