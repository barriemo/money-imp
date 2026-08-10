<?php

namespace App\Domains\Billing\Services;

use App\Models\AccountingInvoice;
use Throwable;

class BulkInvoiceSendService
{
    public function __construct(
        private readonly FreeAgentInvoiceSendService $sender,
    ) {}

    public function send(array $invoiceIds): array
    {
        $result = [
            'requested' => count($invoiceIds),
            'sent' => [],
            'failed' => [],
        ];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = AccountingInvoice::query()
                ->with([
                    'client',
                    'billingReview',
                ])
                ->find($invoiceId);

            if (! $invoice) {
                $result['failed'][] = [
                    'invoice' => $invoiceId,
                    'client' => 'Unknown',
                    'error' => 'Invoice not found.',
                ];

                continue;
            }

            try {
                $this->sender->send($invoice);

                $result['sent'][] = [
                    'invoice' => $invoice->invoice_number,
                    'client' => $invoice->client?->name,
                ];
            } catch (Throwable $exception) {
                $result['failed'][] = [
                    'invoice' => $invoice->invoice_number,
                    'client' => $invoice->client?->name,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }
}
