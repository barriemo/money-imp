<?php

namespace App\Domains\Suppliers\Services;

use App\Models\BankTransaction;
use App\Models\SupplierProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupplierTransactionService
{
    public function forSupplier(
        SupplierProfile $supplier
    ): Collection {
        $key = $this->normalise(
            $supplier->supplier_key
        );

        return BankTransaction::query()
            ->where('amount', '<', 0)
            ->latest('transaction_date')
            ->get()
            ->filter(
                function (
                    BankTransaction $transaction
                ) use ($key): bool {
                    $haystack = $this->normalise(
                        implode(' ', [
                            $transaction->description
                                ?? '',
                            $transaction->reference
                                ?? '',
                            data_get(
                                $transaction->metadata,
                                'merchant',
                                ''
                            ),
                            data_get(
                                $transaction->raw_payload,
                                'merchant',
                                ''
                            ),
                        ])
                    );

                    return $key !== ''
                        && Str::contains(
                            $haystack,
                            $key
                        );
                }
            )
            ->values();
    }

    private function normalise(
        ?string $value
    ): string {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches(
                '/[^a-z0-9]+/',
                ' '
            )
            ->squish()
            ->value();
    }
}
