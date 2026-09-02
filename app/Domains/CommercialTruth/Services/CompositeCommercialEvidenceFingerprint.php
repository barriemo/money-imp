<?php

namespace App\Domains\CommercialTruth\Services;

use App\Models\AccountingInvoiceItem;
use LogicException;

final class CompositeCommercialEvidenceFingerprint
{
    public function forInvoiceItem(
        AccountingInvoiceItem $item
    ): string {
        $invoice =
            $item->relationLoaded('invoice')
                ? $item->invoice
                : $item->invoice()->first();

        if ($invoice === null) {
            throw new LogicException(
                'Composite commercial evidence requires its source invoice.'
            );
        }

        /*
         * Fingerprint the exact commercially material source state
         * presented for structural human review.
         *
         * Do not use the classifier/candidate fingerprint here:
         * several source items may intentionally share that family
         * identity.
         */
        $state = [
            'accounting_invoice_item_id' => (string) $item->id,

            'accounting_invoice_id' => (string) $item->accounting_invoice_id,

            'client_id' => (string) $invoice->client_id,

            'invoice_number' => (string) $invoice->invoice_number,

            'invoice_date' => $invoice->invoice_date !== null
                    ? (string) $invoice->invoice_date
                    : null,

            'invoice_status' => $invoice->status !== null
                    ? (string) $invoice->status
                    : null,

            'description' => (string) $item->description,

            'quantity' => (string) $item->quantity,

            'unit_price' => (string) $item->unit_price,

            'net_amount' => (string) $item->net_amount,

            'tax_rate' => $item->tax_rate !== null
                    ? (string) $item->tax_rate
                    : null,

            'tax_amount' => (string) $item->tax_amount,

            'gross_amount' => (string) $item->gross_amount,
        ];

        return hash(
            'sha256',
            json_encode(
                $state,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
        );
    }

    public function snapshot(
        AccountingInvoiceItem $item
    ): array {
        $invoice =
            $item->relationLoaded('invoice')
                ? $item->invoice
                : $item->invoice()->first();

        if ($invoice === null) {
            throw new LogicException(
                'Composite commercial evidence requires its source invoice.'
            );
        }

        return [
            'accounting_invoice_item_id' => (string) $item->id,

            'accounting_invoice_id' => (string) $item->accounting_invoice_id,

            'invoice_number' => (string) $invoice->invoice_number,

            'invoice_date' => $invoice->invoice_date !== null
                    ? (string) $invoice->invoice_date
                    : null,

            'invoice_status' => $invoice->status !== null
                    ? (string) $invoice->status
                    : null,

            'description' => (string) $item->description,

            'quantity' => (string) $item->quantity,

            'unit_price' => (string) $item->unit_price,

            'net_amount' => (string) $item->net_amount,

            'tax_rate' => $item->tax_rate !== null
                    ? (string) $item->tax_rate
                    : null,

            'tax_amount' => (string) $item->tax_amount,

            'gross_amount' => (string) $item->gross_amount,
        ];
    }
}
