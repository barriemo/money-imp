<?php

namespace App\Domains\Suppliers\Assets\Actions;

use App\Models\SupplierAsset;

class ReviewSupplierAsset
{
    public function execute(
        SupplierAsset $asset,
        string $purpose,
        ?string $clientId,
        bool $billable,
        ?float $expectedCharge,
        ?string $notes
    ): SupplierAsset {
        $asset->update([
            'purpose' => $purpose,

            'client_id' => $purpose === 'client'
                    ? $clientId
                    : null,

            'billable' => $purpose === 'client'
                    ? $billable
                    : false,

            'expected_charge' => $purpose === 'client'
                    && $billable
                        ? $expectedCharge
                        : null,

            'active' => ! in_array(
                $purpose,
                [
                    'dead',
                    'cancel',
                ],
                true
            ),

            'notes' => $notes,
        ]);

        return $asset->refresh();
    }
}
