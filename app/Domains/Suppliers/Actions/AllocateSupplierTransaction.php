<?php

namespace App\Domains\Suppliers\Actions;

use App\Models\BankTransaction;
use App\Models\CostAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllocateSupplierTransaction
{
    public function execute(
        BankTransaction $transaction,
        string $purpose,
        ?string $clientId,
        User $user
    ): void {
        DB::transaction(
            function () use (
                $transaction,
                $purpose,
                $clientId,
                $user
            ): void {
                $transaction->update([
                    'cost_purpose' => $purpose,
                    'cost_review_status' => $purpose === 'unknown'
                            ? 'needs_review'
                            : 'reviewed',
                    'cost_reviewed_at' => now(),
                    'cost_reviewed_by' => $user->id,
                ]);

                CostAllocation::query()
                    ->where(
                        'cost_allocatable_type',
                        BankTransaction::class
                    )
                    ->where(
                        'cost_allocatable_id',
                        $transaction->id
                    )
                    ->delete();

                if (
                    $purpose !== 'client'
                    || ! $clientId
                ) {
                    return;
                }

                CostAllocation::create([
                    'cost_allocatable_type' => BankTransaction::class,
                    'cost_allocatable_id' => $transaction->id,

                    'client_id' => $clientId,

                    'amount' => abs(
                        (float) $transaction->amount
                    ),

                    'allocation_percent' => 100,

                    'allocated_at' => now(),
                    'allocated_by' => $user->id,

                    'metadata' => [
                        'source' => 'supplier_review',
                    ],
                ]);
            }
        );
    }
}
