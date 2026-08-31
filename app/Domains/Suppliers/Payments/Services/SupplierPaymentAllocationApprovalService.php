<?php

namespace App\Domains\Suppliers\Payments\Services;

use App\Models\AccountingBill;
use App\Models\BankTransaction;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentAllocationApprovalService
{
    public function approve(
        SupplierPaymentAllocation $allocation,
        string $userId
    ): SupplierPaymentAllocation {
        return DB::transaction(
            function () use (
                $allocation,
                $userId
            ): SupplierPaymentAllocation {
                $allocation = SupplierPaymentAllocation::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->id);

                if ($allocation->status !== 'suggested') {
                    throw ValidationException::withMessages([
                        'allocation' => 'This supplier payment suggestion is no longer awaiting approval.',
                    ]);
                }

                $transaction = BankTransaction::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $allocation->bank_transaction_id
                    );

                $bill = AccountingBill::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $allocation->accounting_bill_id
                    );

                if (
                    (float) $transaction->amount >= 0
                    || (float) $bill->outstanding_amount <= 0
                ) {
                    throw ValidationException::withMessages([
                        'allocation' => 'The supplier payment or bill is no longer available for allocation.',
                    ]);
                }

                $transactionAllocated =
                    (float) $transaction
                        ->supplierPaymentAllocations()
                        ->whereIn(
                            'status',
                            ['approved', 'imported']
                        )
                        ->where(
                            'id',
                            '!=',
                            $allocation->id
                        )
                        ->sum('amount');

                $paymentAvailable = max(
                    0,
                    abs((float) $transaction->amount)
                        - $transactionAllocated
                );

                $amount = min(
                    (float) $allocation->amount,
                    $paymentAvailable,
                    (float) $bill->outstanding_amount
                );

                if ($amount <= 0) {
                    throw ValidationException::withMessages([
                        'allocation' => 'There is no remaining payment or bill balance available.',
                    ]);
                }

                $allocation->update([
                    'amount' => $amount,
                    'status' => 'approved',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ]);

                $bill->update([
                    'paid_amount' => (float) $bill->paid_amount + $amount,
                    'outstanding_amount' => max(
                        0,
                        (float) $bill->outstanding_amount - $amount
                    ),
                ]);

                $totalAllocated =
                    (float) $transaction
                        ->supplierPaymentAllocations()
                        ->whereIn(
                            'status',
                            ['approved', 'imported']
                        )
                        ->sum('amount');

                $transaction->update([
                    'match_status' => $totalAllocated + 0.01
                            >= abs((float) $transaction->amount)
                            ? 'reconciled'
                            : 'partially_allocated',
                    'matched_at' => now(),
                    'matched_by' => $userId,
                ]);

                return $allocation->fresh();
            }
        );
    }

    public function reject(
        SupplierPaymentAllocation $allocation,
        string $userId,
        ?string $reason = null
    ): SupplierPaymentAllocation {
        return DB::transaction(
            function () use (
                $allocation,
                $userId,
                $reason
            ): SupplierPaymentAllocation {
                $allocation = SupplierPaymentAllocation::query()
                    ->lockForUpdate()
                    ->findOrFail($allocation->id);

                if ($allocation->status !== 'suggested') {
                    throw ValidationException::withMessages([
                        'allocation' => 'This supplier payment suggestion is no longer awaiting review.',
                    ]);
                }

                $metadata = $allocation->metadata ?? [];

                if (
                    $reason !== null
                    && trim($reason) !== ''
                ) {
                    $metadata['rejection_reason'] =
                        trim($reason);
                }

                $metadata['rejected_at'] =
                    now()->toIso8601String();

                $metadata['rejected_by'] = $userId;

                $allocation->update([
                    'status' => 'rejected',
                    'metadata' => $metadata,
                ]);

                return $allocation->fresh();
            }
        );
    }
}
