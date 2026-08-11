<?php

namespace App\Domains\Suppliers\Rules;

use App\Domains\Suppliers\Actions\AllocateSupplierTransaction;
use App\Models\BankTransaction;
use App\Models\User;

class SupplierAttributionAutoApplier
{
    public function __construct(
        private SupplierAttributionRuleMatcher $matcher,
        private AllocateSupplierTransaction $allocate
    ) {}

    public function apply(
        BankTransaction $transaction,
        ?User $user = null
    ): bool {
        if ((float) $transaction->amount >= 0) {
            return false;
        }

        $rule = $this->matcher
            ->bestMatch($transaction);

        if (! $rule) {
            return false;
        }

        if (! $user) {
            $transaction->update([
                'cost_purpose' => $rule->purpose,

                'cost_review_status' => 'auto_suggested',

                'metadata' => array_merge(
                    $transaction->metadata ?? [],
                    [
                        'supplier_rule_id' => $rule->id,

                        'supplier_rule_confidence' => $rule->confidence,
                    ]
                ),
            ]);

            return true;
        }

        $this->allocate->execute(
            $transaction,
            $rule->purpose,
            $rule->client_id,
            $user
        );

        $transaction->update([
            'metadata' => array_merge(
                $transaction->metadata ?? [],
                [
                    'supplier_rule_id' => $rule->id,

                    'supplier_rule_confidence' => $rule->confidence,

                    'auto_attributed' => true,
                ]
            ),
        ]);

        return true;
    }
}
