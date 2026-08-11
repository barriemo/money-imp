<?php

namespace App\Domains\Suppliers\Rules;

use App\Domains\Suppliers\Actions\AllocateSupplierTransaction;
use App\Models\BankTransaction;
use App\Models\SupplierAttributionRule;
use App\Models\User;

class SupplierAttributionRuleApplier
{
    public function __construct(
        private AllocateSupplierTransaction $allocate
    ) {}

    public function apply(
        SupplierAttributionRule $rule,
        User $user
    ): int {
        $count = 0;

        BankTransaction::query()
            ->where('amount', '<', 0)
            ->orderBy('transaction_date')
            ->get()
            ->each(
                function (
                    BankTransaction $transaction
                ) use (
                    $rule,
                    $user,
                    &$count
                ): void {
                    if (
                        ! $this->matches(
                            $rule,
                            $transaction
                        )
                    ) {
                        return;
                    }

                    $this->allocate->execute(
                        $transaction,
                        $rule->purpose,
                        $rule->client_id,
                        $user
                    );

                    $count++;
                }
            );

        $rule->update([
            'last_applied_at' => now(),
        ]);

        return $count;
    }

    private function matches(
        SupplierAttributionRule $rule,
        BankTransaction $transaction
    ): bool {
        $matcher = app(
            SupplierAttributionRuleMatcher::class
        );

        return $matcher
            ->matchingRules($transaction)
            ->contains(
                fn (
                    SupplierAttributionRule $candidate
                ) => $candidate->id === $rule->id
            );
    }
}
