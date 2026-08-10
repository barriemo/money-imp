<?php

namespace App\Domains\MoneyOut\Services;

use App\Models\ImportRow;
use App\Models\SupplierAlias;

class MoneyOutCategorisationService
{
    public function __construct(
        private readonly MerchantNormaliser $normaliser,
    ) {}

    public function categorise(
        ImportRow $row
    ): ImportRow {
        $identity = $this->normaliser->normalise(
            $row->merchant ?: $row->description
        );

        if ($identity === '') {
            return $row;
        }

        $alias = SupplierAlias::query()
            ->with('supplier')
            ->where('normalised_alias', $identity)
            ->orderByDesc('confidence')
            ->first();

        if (! $alias) {
            $row->update([
                'classification_status' => 'needs_review',
                'classification_confidence' => null,
            ]);

            return $row->refresh();
        }

        $supplier = $alias->supplier;

        $row->update([
            'supplier_id' => $supplier->id,
            'classification_status' => 'suggested',
            'classification_confidence' => $alias->confidence,
        ]);

        $alias->increment('successful_matches');

        $alias->update([
            'last_matched_at' => now(),
        ]);

        return $row->refresh();
    }
}
