<?php

namespace App\Domains\Infrastructure\Billing;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class InfrastructureBillingMatcher
{
    public function latestHostingLine(
        Client $client
    ): ?object {
        return DB::table(
            'accounting_invoice_items as items'
        )
            ->join(
                'accounting_invoices as invoices',
                'invoices.id',
                '=',
                'items.accounting_invoice_id'
            )
            ->where(
                'invoices.client_id',
                $client->id
            )
            ->where(function ($query): void {
                foreach ([
                    'monthly hosting',
                    'website hosting',
                    'managed hosting',
                    'hosting',
                    'server',
                    'vps',
                ] as $keyword) {
                    $query->orWhereRaw(
                        'LOWER(items.description) LIKE ?',
                        [
                            '%'.$keyword.'%',
                        ]
                    );
                }
            })
            ->orderByDesc(
                'invoices.invoice_date'
            )
            ->orderByDesc(
                'items.created_at'
            )
            ->select([
                'items.id',
                'items.description',
                'items.quantity',
                'items.unit_price',
                'items.net_amount',
                'invoices.invoice_number',
                'invoices.invoice_date',
            ])
            ->first();
    }
}
