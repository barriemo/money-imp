<?php

namespace App\Domains\Infrastructure\Discovery;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientServiceAssetDiscoveryService
{
    public function discover(
        Client $client
    ): Collection {
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
            ->orderByDesc(
                'invoices.invoice_date'
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
            ->get()
            ->map(
                fn (object $item) => $this->classify($item)
            )
            ->filter()
            ->unique(
                fn (array $item) => $item['type']
                    .'|'
                    .$item['key']
            )
            ->values();
    }

    private function classify(
        object $item
    ): ?array {
        $description = trim(
            $item->description ?? ''
        );

        $normalised = Str::lower(
            $description
        );

        if (
            str_contains(
                $normalised,
                'postmark'
            )
        ) {
            return $this->proposal(
                item: $item,
                type: 'email_delivery',
                key: 'postmark',
                name: 'Postmark',
                confidence: 100
            );
        }

        if (
            str_contains(
                $normalised,
                'google workspace'
            )
        ) {
            return $this->proposal(
                item: $item,
                type: 'workspace',
                key: 'google-workspace',
                name: 'Google Workspace',
                confidence: 100
            );
        }

        if (
            str_contains(
                $normalised,
                'domain'
            )
        ) {
            $domain = $this->extractDomain(
                $description
            );

            if ($domain) {
                return $this->proposal(
                    item: $item,
                    type: 'domain',
                    key: $domain,
                    name: $domain,
                    confidence: 100
                );
            }
        }

        if (
            str_contains(
                $normalised,
                'hosting'
            )
        ) {
            return $this->proposal(
                item: $item,
                type: 'hosting',
                key: 'hosting',
                name: 'Hosting',
                confidence: 95
            );
        }

        return null;
    }

    private function extractDomain(
        string $text
    ): ?string {
        if (
            ! preg_match(
                '/\b(?:[a-z0-9-]+\.)+(?:co\.uk|org\.uk|me\.uk|com|net|org|io|ai|app|uk)\b/i',
                $text,
                $match
            )
        ) {
            return null;
        }

        return Str::lower(
            trim(
                $match[0],
                " .,\t\n\r"
            )
        );
    }

    private function proposal(
        object $item,
        string $type,
        string $key,
        string $name,
        int $confidence
    ): array {
        return [
            'type' => $type,
            'key' => $key,
            'name' => $name,

            'confidence' => $confidence,

            'description' => $item->description,

            'unit_price' => (float) $item->unit_price,

            'quantity' => (float) $item->quantity,

            'net_amount' => (float) $item->net_amount,

            'invoice_number' => $item->invoice_number,

            'invoice_date' => $item->invoice_date,

            'invoice_item_id' => $item->id,
        ];
    }
}
