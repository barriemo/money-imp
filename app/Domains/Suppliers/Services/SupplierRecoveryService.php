<?php

namespace App\Domains\Suppliers\Services;

use App\Models\SupplierProfile;

class SupplierRecoveryService
{
    public function __construct(
        private SupplierAnalysisService $analysis
    ) {}

    public function potentialRecovery(
        SupplierProfile $supplier
    ): float {
        return $this->analysis
            ->analyse($supplier)
            ->potentialRecovery;
    }
}
