<?php

namespace App\Domains\Suppliers\Rules;

use App\Models\BankTransaction;
use App\Models\SupplierAttributionRule;
use App\Models\SupplierProfile;
use Illuminate\Support\Str;

class SupplierAttributionRuleLearner
{
    public function learn(
        SupplierProfile $supplier,
        BankTransaction $transaction,
        string $purpose,
        ?string $clientId = null,
        bool $applyHistorically = true
    ): SupplierAttributionRule {
        $matchValue = $this->suggestMatchValue(
            $transaction
        );

        return SupplierAttributionRule::updateOrCreate(
            [
                'supplier_profile_id' => $supplier->id,

                'match_type' => 'contains',

                'match_value' => $matchValue,

                'purpose' => $purpose,

                'client_id' => $clientId,
            ],
            [
                'confidence' => 100,

                'apply_historically' => $applyHistorically,

                'active' => true,

                'metadata' => [
                    'learned_from_transaction_id' => $transaction->id,
                ],
            ]
        );
    }

    private function suggestMatchValue(
        BankTransaction $transaction
    ): string {
        return Str::of(
            $transaction->description
                ?: $transaction->reference
                ?: ''
        )
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ' '
            )
            ->squish()
            ->value();
    }
}
