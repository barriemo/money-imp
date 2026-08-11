<?php

namespace App\Domains\Suppliers\Rules;

use App\Models\BankTransaction;
use App\Models\SupplierAttributionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupplierAttributionRuleMatcher
{
    public function matchingRules(
        BankTransaction $transaction
    ): Collection {
        $haystack = $this->haystack(
            $transaction
        );

        return SupplierAttributionRule::query()
            ->where('active', true)
            ->get()
            ->filter(
                function (
                    SupplierAttributionRule $rule
                ) use ($haystack): bool {
                    $needle = $this->normalise(
                        $rule->match_value
                    );

                    if ($needle === '') {
                        return false;
                    }

                    return match ($rule->match_type) {
                        'exact' => $haystack === $needle,

                        'contains' => Str::contains(
                            $haystack,
                            $needle
                        ),

                        default => false,
                    };
                }
            )
            ->values();
    }

    public function bestMatch(
        BankTransaction $transaction
    ): ?SupplierAttributionRule {
        return $this->matchingRules(
            $transaction
        )
            ->sortByDesc('confidence')
            ->first();
    }

    private function haystack(
        BankTransaction $transaction
    ): string {
        return $this->normalise(
            implode(' ', [
                $transaction->description ?? '',
                $transaction->reference ?? '',
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
    }

    private function normalise(
        ?string $value
    ): string {
        return Str::of(
            $value ?? ''
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
