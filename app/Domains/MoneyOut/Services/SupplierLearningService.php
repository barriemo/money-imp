<?php

namespace App\Domains\MoneyOut\Services;

use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\ImportRow;
use App\Models\Supplier;
use App\Models\SupplierAlias;

class SupplierLearningService
{
    public function __construct(
        private readonly MerchantNormaliser $normaliser,
    ) {}

    public function confirm(
        ImportRow $row,
        Supplier $supplier,
        ExpenseCategory $category,
        ?Client $client,
        bool $remember,
        ?int $userId
    ): ImportRow {
        $identity = $this->normaliser->normalise(
            $row->merchant ?: $row->description
        );

        $row->update([
            'supplier_id' => $supplier->id,
            'expense_category_id' => $category->id,
            'client_id' => $client?->id,
            'classification_status' => 'reviewed',
            'classification_confidence' => 100,
            'remember_classification' => $remember,
            'reviewed_at' => now(),
            'reviewed_by' => $userId,
        ]);

        if ($remember && $identity !== '') {
            SupplierAlias::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'normalised_alias' => $identity,
                ],
                [
                    'alias' => $row->merchant
                        ?: $row->description,
                    'source_type' => 'money_out_review',
                    'confidence' => 100,
                    'last_matched_at' => now(),
                ]
            );
        }

        return $row->refresh();
    }
}
