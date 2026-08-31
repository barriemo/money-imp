<?php

namespace App\Domains\Suppliers\Payments\Services;

use App\Models\AccountingBill;
use App\Models\BankTransaction;
use App\Models\Supplier;
use App\Models\SupplierAlias;
use App\Models\SupplierPaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SupplierPaymentCandidateService
{
    public function generate(): array
    {
        $stats = [
            'considered' => 0,
            'supplier_matches' => 0,
            'bill_matches' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
        ];

        BankTransaction::query()
            ->where('amount', '<', 0)
            ->whereIn('match_status', [
                'unmatched',
                'partially_allocated',
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('cost_review_status')
                    ->orWhere('cost_review_status', '!=', 'ignored');
            })
            ->with('bankAccount')
            ->chunkById(200, function ($transactions) use (&$stats): void {
                foreach ($transactions as $transaction) {
                    $stats['considered']++;

                    $supplierCandidate = $this->bestSupplierCandidate(
                        $transaction
                    );

                    if (! $supplierCandidate) {
                        $stats['unmatched']++;

                        continue;
                    }

                    if ($supplierCandidate['ambiguous']) {
                        $stats['ambiguous']++;

                        continue;
                    }

                    /** @var Supplier $supplier */
                    $supplier = $supplierCandidate['supplier'];

                    $stats['supplier_matches']++;

                    $billCandidate = $this->bestBillCandidate(
                        $transaction,
                        $supplier
                    );

                    if (! $billCandidate) {
                        continue;
                    }

                    $remainingPayment = $this->remainingPayment(
                        $transaction
                    );

                    if ($remainingPayment <= 0) {
                        continue;
                    }

                    $suggestedAllocation =
                        SupplierPaymentAllocation::query()
                            ->where(
                                'bank_transaction_id',
                                $transaction->id
                            )
                            ->where(
                                'accounting_bill_id',
                                $billCandidate['bill']->id
                            )
                            ->where(
                                'status',
                                'suggested'
                            )
                            ->first();

                    $suggestedAmount = min(
                        $remainingPayment,
                        (float) $billCandidate['bill']->outstanding_amount
                    );

                    if ($suggestedAllocation) {
                        $suggestedAllocation->update([
                            'amount' => $suggestedAmount,
                            'confidence' => min(
                                100,
                                $supplierCandidate['confidence']
                                    + $billCandidate['bonus']
                            ),
                            'match_method' => $billCandidate['method'],
                            'reason' => $billCandidate['reason'],
                            'metadata' => [
                                'supplier_match_method' => $supplierCandidate['method'],
                                'supplier_match_confidence' => $supplierCandidate['confidence'],
                            ],
                        ]);
                    } else {
                        SupplierPaymentAllocation::create([
                            'bank_transaction_id' => $transaction->id,
                            'accounting_bill_id' => $billCandidate['bill']->id,
                            'amount' => $suggestedAmount,
                            'status' => 'suggested',
                            'confidence' => min(
                                100,
                                $supplierCandidate['confidence']
                                    + $billCandidate['bonus']
                            ),
                            'match_method' => $billCandidate['method'],
                            'reason' => $billCandidate['reason'],
                            'metadata' => [
                                'supplier_match_method' => $supplierCandidate['method'],
                                'supplier_match_confidence' => $supplierCandidate['confidence'],
                            ],
                        ]);
                    }

                    $stats['bill_matches']++;
                }
            });

        return $stats;
    }

    private function remainingPayment(
        BankTransaction $transaction
    ): float {
        $allocated = (float) $transaction
            ->supplierPaymentAllocations()
            ->whereIn('status', ['approved', 'imported'])
            ->sum('amount');

        return max(
            0,
            abs((float) $transaction->amount) - $allocated
        );
    }

    private function bestSupplierCandidate(
        BankTransaction $transaction
    ): ?array {
        $text = $this->haystack($transaction);

        if ($text === '') {
            return null;
        }

        $candidates = collect();

        foreach (
            SupplierAlias::query()
                ->with('supplier')
                ->get() as $alias
        ) {
            $needle = $this->normalise($alias->normalised_alias);

            if (
                $needle === ''
                || ! Str::contains($text, $needle)
                || ! $alias->supplier
            ) {
                continue;
            }

            $candidates->push([
                'supplier' => $alias->supplier,
                'confidence' => (float) $alias->confidence,
                'method' => 'supplier_alias',
            ]);
        }

        foreach (
            Supplier::query()
                ->where('status', 'active')
                ->get() as $supplier
        ) {
            $needle = $this->normalise($supplier->name);

            if (
                strlen($needle) < 4
                || ! Str::contains($text, $needle)
            ) {
                continue;
            }

            $candidates->push([
                'supplier' => $supplier,
                'confidence' => 85,
                'method' => 'supplier_name',
            ]);
        }

        $ranked = $candidates
            ->groupBy(
                fn (array $candidate) => $candidate['supplier']->id
            )
            ->map(function (Collection $matches): array {
                $best = $matches
                    ->sortByDesc('confidence')
                    ->first();

                return $best;
            })
            ->sortByDesc('confidence')
            ->values();

        if ($ranked->isEmpty()) {
            return null;
        }

        $first = $ranked->first();
        $second = $ranked->skip(1)->first();

        return [
            'supplier' => $first['supplier'],
            'confidence' => $first['confidence'],
            'method' => $first['method'],
            'ambiguous' => $second !== null
                && abs(
                    $first['confidence'] - $second['confidence']
                ) < 15,
        ];
    }

    private function bestBillCandidate(
        BankTransaction $transaction,
        Supplier $supplier
    ): ?array {
        $amount = abs((float) $transaction->amount);

        $bills = AccountingBill::query()
            ->where('supplier_id', $supplier->id)
            ->where('outstanding_amount', '>', 0)
            ->get();

        $candidates = collect();

        foreach ($bills as $bill) {
            $outstanding = (float) $bill->outstanding_amount;
            $gross = (float) $bill->gross_amount;

            if (
                abs($outstanding - $amount) < 0.01
                || abs($gross - $amount) < 0.01
            ) {
                $candidates->push([
                    'bill' => $bill,
                    'bonus' => 15,
                    'method' => 'exact_amount',
                    'reason' => 'Payment exactly matches the outstanding supplier bill.',
                ]);

                continue;
            }

            if (
                $bill->bill_date
                && $bill->bill_date->lte($transaction->transaction_date)
            ) {
                $difference = abs($outstanding - $amount);

                if ($difference <= 1.00) {
                    $candidates->push([
                        'bill' => $bill,
                        'bonus' => 8,
                        'method' => 'amount_with_tolerance',
                        'reason' => 'Payment is within £1.00 of the outstanding supplier bill.',
                    ]);
                }
            }
        }

        return $candidates
            ->sortByDesc('bonus')
            ->first();
    }

    private function haystack(
        BankTransaction $transaction
    ): string {
        return $this->normalise(
            implode(' ', [
                $transaction->description ?? '',
                $transaction->reference ?? '',
                $transaction->counterparty_name ?? '',
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

    private function normalise(?string $value): string
    {
        return Str::of($value ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();
    }
}
