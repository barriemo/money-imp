<?php

namespace App\Domains\Suppliers\Services;

use App\Models\SupplierProfile;

class SupplierRecoveryService
{
    public function __construct(
        private SupplierAnalysisService $analysis
    ) {}

    public function leakage(
        SupplierProfile $supplier
    ): float {
        if (! $supplier->recoverable) {
            return 0.0;
        }

        return $this->analysis
            ->analyse($supplier)
            ->unallocatedSpend;
    }
}
