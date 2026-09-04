<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Historical;

use App\Models\AccountingInvoice;
use App\Models\BankTransaction;
use App\Models\PaymentAllocation;

class HistoricalPaymentVerificationService
{
    public function generate(): array
    {
        $stats = [
            'considered' => 0,
            'created' => 0,
            'ambiguous' => 0,
            'no_match' => 0,
            'already_allocated' => 0,
            'value_suggested' => 0.0,
        ];

        BankTransaction::query()
            ->where(
                'transaction_type',
                'customer_payment'
            )
            ->where(
                'amount',
                '>',
                0
            )
            ->whereNotNull(
                'client_id'
            )
            ->where(
                function ($query): void {
                    $query
                        ->where(
                            'match_status',
                            '!=',
                            'suggested'
                        )
                        ->orWhereNull(
                            'match_status'
                        )
                        ->orWhereNotNull(
                            'matched_by'
                        );
                }
            )
            ->chunkById(
                200,
                function ($transactions) use (&$stats): void {
                    foreach ($transactions as $transaction) {
                        $stats['considered']++;

                        if (
                            $transaction
                                ->paymentAllocations()
                                ->exists()
                        ) {
                            $stats['already_allocated']++;

                            continue;
                        }

                        $candidates =
                            $this->candidates(
                                $transaction
                            );

                        if ($candidates->isEmpty()) {
                            $stats['no_match']++;

                            continue;
                        }

                        if ($candidates->count() !== 1) {
                            $stats['ambiguous']++;

                            continue;
                        }

                        $invoice =
                            $candidates->first();

                        PaymentAllocation::create([
                            'bank_transaction_id' => $transaction->id,

                            'accounting_invoice_id' => $invoice->id,

                            'amount' => round(
                                (float) $transaction->amount,
                                2
                            ),

                            'status' => 'suggested',

                            'confidence' => 100,

                            'match_method' => 'historical_client_exact_amount',

                            'reason' => 'Historical bank payment has exactly one same-client invoice with the same gross amount issued on or before the payment date.',

                            'metadata' => [
                                'historical_verification' => true,

                                'accounting_invoice_status' => $invoice
                                    ->status,

                                'accounting_outstanding_amount' => (float) $invoice
                                    ->outstanding_amount,
                            ],
                        ]);

                        $stats['created']++;

                        $stats['value_suggested'] =
                            round(
                                $stats['value_suggested']
                                + (float) $transaction->amount,
                                2
                            );
                    }
                }
            );

        return $stats;
    }

    private function candidates(
        BankTransaction $transaction
    ) {
        return AccountingInvoice::query()
            ->where(
                'client_id',
                $transaction->client_id
            )
            ->where(
                'gross_amount',
                '>',
                0
            )
            ->whereRaw(
                'ABS(gross_amount - ?) < 0.01',
                [
                    (float) $transaction->amount,
                ]
            )
            ->where(
                function ($query) use ($transaction): void {
                    $query
                        ->whereNull(
                            'invoice_date'
                        )
                        ->orWhereDate(
                            'invoice_date',
                            '<=',
                            $transaction->transaction_date
                        );
                }
            )
            ->orderBy(
                'invoice_date'
            )
            ->get();
    }
}
