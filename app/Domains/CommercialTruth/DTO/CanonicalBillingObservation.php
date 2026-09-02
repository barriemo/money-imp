<?php

namespace App\Domains\CommercialTruth\DTO;

final class CanonicalBillingObservation
{
    public function __construct(
        public readonly string $invoice_item_id,
        public readonly float $quantity,
        public readonly float $unit_price,
        public readonly float $net_amount,
        public readonly ?string $created_at,
        public readonly ?string $invoice_date,
        public readonly string $client_service_id,
        public readonly string $client_id,
        public readonly string $service_name,
        public readonly string $service_status,
        public readonly string $client_name,
        public readonly string $attribution_source,
        public readonly ?string $allocation_set_id = null,
        public readonly ?string $allocation_id = null,
    ) {}
}
