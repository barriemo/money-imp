<?php

namespace App\Domains\Suppliers\DTOs;

use App\Models\SupplierProfile;

readonly class SupplierAnalysis
{
    public function __construct(
        public SupplierProfile $supplier,
        public int $transactionCount,
        public float $totalSpend,
        public float $last30DaysSpend,
        public float $averageMonthlySpend,
        public float $annualisedSpend,
        public float $allocatedSpend,
        public float $clientSpend,
        public float $internalSpend,
        public float $sharedSpend,
        public float $wasteSpend,
        public float $unknownSpend,
        public float $potentialRecovery,
        public bool $recurring,
        public ?string $lastSeen,
    ) {}
}
