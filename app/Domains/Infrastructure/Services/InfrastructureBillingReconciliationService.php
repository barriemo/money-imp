<?php

namespace App\Domains\Infrastructure\Services;

use App\Domains\Infrastructure\DTOs\InfrastructureBillingReconciliation;
use App\Models\SupplierAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InfrastructureBillingReconciliationService
{
    public function reconcile(
        SupplierAsset $asset
    ): InfrastructureBillingReconciliation {
        $asset->loadMissing([
            'client',
            'supplier',
        ]);

        $cost = round(
            (float) $asset->observed_cost,
            2
        );

        if (
            $asset->purpose !== 'client'
            || ! $asset->client_id
            || ! $asset->billable
        ) {
            return $this->result(
                asset: $asset,
                status: 'UNKNOWN',
                cost: $cost,
                recovery: 0,
                confidence: 'not_applicable'
            );
        }

        $keywords = $this->keywordsFor(
            $asset
        );

        if ($keywords === []) {
            return $this->result(
                asset: $asset,
                status: 'UNKNOWN',
                cost: $cost,
                recovery: 0,
                confidence: 'no_billing_rule'
            );
        }

        $items = DB::table(
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
                $asset->client_id
            )
            ->where(function ($query) use (
                $keywords
            ): void {
                foreach (
                    $keywords as $keyword
                ) {
                    $query->orWhereRaw(
                        'LOWER(items.description) LIKE ?',
                        [
                            '%'
                            .strtolower($keyword)
                            .'%',
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
                'items.description',
                'items.quantity',
                'items.unit_price',
                'items.net_amount',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.status',
            ])
            ->get();

        if ($items->isEmpty()) {
            return $this->result(
                asset: $asset,
                status: 'MISSING',
                cost: $cost,
                recovery: 0,
                confidence: 'high'
            );
        }

        /*
         * For explicit recurring service lines,
         * unit_price is the monthly rate.
         *
         * Example:
         * "Monthly Hosting April & May 26"
         * quantity 2 × unit price £185
         * means £185/month, not £370/month.
         */
        $latest = $items->first();

        $recovery = round(
            (float) $latest->unit_price,
            2
        );

        $status = match (true) {
            $recovery <= 0 => 'MISSING',

            $recovery < $cost => 'UNDER_RECOVERED',

            default => 'COVERED',
        };

        return $this->result(
            asset: $asset,
            status: $status,
            cost: $cost,
            recovery: $recovery,
            description: $latest->description,
            invoiceDate: $latest->invoice_date,
            invoiceNumber: $latest->invoice_number,
            confidence: 'high'
        );
    }

    private function keywordsFor(
        SupplierAsset $asset
    ): array {
        $type = Str::lower(
            $asset->asset_type
        );

        if (
            in_array(
                $type,
                [
                    'hosting_server',
                    'hosting_addon',
                    'storage',
                ],
                true
            )
        ) {
            return [
                'monthly hosting',
                'website hosting',
                'managed hosting',
                'hosting',
                'server',
                'vps',
            ];
        }

        if ($type === 'domain') {
            return [
                'domain renewal',
                'domain annual renewal',
                'domain',
            ];
        }

        return [];
    }

    private function result(
        SupplierAsset $asset,
        string $status,
        float $cost,
        float $recovery,
        ?string $description = null,
        ?string $invoiceDate = null,
        ?string $invoiceNumber = null,
        string $confidence = 'unknown'
    ): InfrastructureBillingReconciliation {
        $margin = round(
            $recovery - $cost,
            2
        );

        $gap = round(
            max(
                0,
                $cost - $recovery
            ),
            2
        );

        $coverage = $cost > 0
            ? round(
                ($recovery / $cost) * 100,
                2
            )
            : 0.0;

        return new InfrastructureBillingReconciliation(
            asset: $asset,
            status: $status,
            monthlyCost: $cost,
            monthlyRecovery: $recovery,
            monthlyMargin: $margin,
            monthlyGap: $gap,
            coveragePercent: $coverage,
            matchedDescription: $description,
            matchedInvoiceDate: $invoiceDate,
            matchedInvoiceNumber: $invoiceNumber,
            confidence: $confidence,
        );
    }
}
