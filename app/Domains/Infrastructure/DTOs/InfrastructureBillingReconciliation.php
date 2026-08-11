<?php

namespace App\Domains\Infrastructure\DTOs;

use App\Models\SupplierAsset;

readonly class InfrastructureBillingReconciliation
{
    public function __construct(
        public SupplierAsset $asset,
        public string $status,
        public float $monthlyCost,
        public float $monthlyRecovery,
        public float $monthlyMargin,
        public float $monthlyGap,
        public float $coveragePercent,
        public ?string $matchedDescription,
        public ?string $matchedInvoiceDate,
        public ?string $matchedInvoiceNumber,
        public string $confidence,
    ) {}
}
