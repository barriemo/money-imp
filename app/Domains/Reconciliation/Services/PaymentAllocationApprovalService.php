<?php

namespace App\Domains\Reconciliation\Services;

use App\Domains\Accounting\Services\InvoiceBalanceService;
use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationApprovalService
{
    public function __construct(
        private readonly InvoiceBalanceService $balances,
    ) {}

    public function approveManual(
        BankTransaction $transaction,
        AccountingInvoice $invoice,
        float $requestedAmount,
        string $userId,
    ): PaymentAllocation {
        return DB::transaction(function () use (
            $transaction,
            $invoice,
            $requestedAmount,
            $userId
        ): PaymentAllocation {
            $transaction = BankTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $invoice = AccountingInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if (
                $transaction->client_id === null
                || $invoice->client_id !== $transaction->client_id
            ) {
                throw ValidationException::withMessages([
                    'allocation' => 'The payment and invoice must belong to the same client.',
                ]);
            }

            if ($requestedAmount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The allocation amount must be greater than zero.',
                ]);
            }

            $transactionAllocated = (float) $transaction
                ->paymentAllocations()
                ->whereIn('status', ['approved', 'imported'])
                ->sum('amount');

            $paymentAvailable = max(
                0,
                (float) $transaction->amount - $transactionAllocated
            );

            $invoiceOutstanding = (float) $this->balances
                ->outstanding($invoice);

            $amount = min(
                $requestedAmount,
                $paymentAvailable,
                $invoiceOutstanding
            );

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'allocation' => 'There is no remaining payment or invoice balance available to allocate.',
                ]);
            }

            $allocation = PaymentAllocation::updateOrCreate(
                [
                    'bank_transaction_id' => $transaction->id,
                    'accounting_invoice_id' => $invoice->id,
                ],
                [
                    'amount' => $amount,
                    'status' => 'approved',
                    'confidence' => 100,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'match_method' => 'manual_reconciliation',
                    'reason' => 'Approved manually in Money Imp reconciliation inbox.',
                ]
            );

            $totalAllocated = (float) $transaction
                ->paymentAllocations()
                ->whereIn('status', ['approved', 'imported'])
                ->sum('amount');

            $transaction->update([
                'match_status' => $totalAllocated + 0.01
                    >= (float) $transaction->amount
                    ? 'reconciled'
                    : 'partially_allocated',
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            return $allocation->fresh();
        });
    }

    public function approve(
        PaymentAllocation $allocation,
        string $userId,
    ): PaymentAllocation {
        return \DB::transaction(function () use ($allocation, $userId): PaymentAllocation {
            $allocation = PaymentAllocation::query()
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($allocation->status !== 'suggested') {
                throw ValidationException::withMessages([
                    'allocation' => 'This payment suggestion is no longer awaiting approval.',
                ]);
            }

            $transaction = $allocation->transaction()
                ->lockForUpdate()
                ->firstOrFail();

            $invoice = $allocation->invoice()
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $transaction->client_id === null
                || $invoice->client_id !== $transaction->client_id
            ) {
                throw ValidationException::withMessages([
                    'allocation' => 'The payment and invoice no longer belong to the same client.',
                ]);
            }

            $transactionAllocated = (float) $transaction
                ->paymentAllocations()
                ->whereIn('status', ['approved', 'imported'])
                ->where('id', '!=', $allocation->id)
                ->sum('amount');

            $paymentAvailable = max(
                0,
                (float) $transaction->amount - $transactionAllocated
            );

            $invoiceOutstanding = (float) $this->balances->outstanding($invoice);

            $amount = min(
                (float) $allocation->amount,
                $paymentAvailable,
                $invoiceOutstanding
            );

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'allocation' => 'There is no remaining payment or invoice balance available for this suggestion.',
                ]);
            }

            $allocation->update([
                'amount' => $amount,
                'status' => 'approved',
                'confidence' => $allocation->confidence,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $totalAllocated = (float) $transaction
                ->paymentAllocations()
                ->whereIn('status', ['approved', 'imported'])
                ->sum('amount');

            $transaction->update([
                'match_status' => $totalAllocated + 0.01 >= (float) $transaction->amount
                    ? 'reconciled'
                    : 'partially_allocated',
                'matched_by' => $userId,
                'matched_at' => now(),
            ]);

            return $allocation->fresh();
        });
    }

    public function reject(
        PaymentAllocation $allocation,
        string $userId,
        ?string $reason = null,
    ): PaymentAllocation {
        return \DB::transaction(function () use (
            $allocation,
            $userId,
            $reason
        ): PaymentAllocation {
            $allocation = PaymentAllocation::query()
                ->lockForUpdate()
                ->findOrFail($allocation->id);

            if ($allocation->status !== 'suggested') {
                throw ValidationException::withMessages([
                    'allocation' => 'This payment suggestion is no longer awaiting review.',
                ]);
            }

            $metadata = $allocation->metadata ?? [];

            if ($reason !== null && trim($reason) !== '') {
                $metadata['rejection_reason'] = trim($reason);
            }

            $metadata['rejected_at'] = now()->toIso8601String();
            $metadata['rejected_by'] = $userId;

            $allocation->update([
                'status' => 'rejected',
                'metadata' => $metadata,
            ]);

            return $allocation->fresh();
        });
    }
}
