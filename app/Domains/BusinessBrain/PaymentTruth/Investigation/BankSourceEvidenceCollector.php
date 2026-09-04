<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Investigation;

use App\Domains\BusinessBrain\Investigation\EvidenceCollector;
use App\Domains\BusinessBrain\Investigation\EvidenceItem;
use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Models\AccountingInvoice;
use App\Models\BankAccount;
use App\Models\BankTransaction;

class BankSourceEvidenceCollector implements EvidenceCollector
{
    public function collect(
        Hypothesis $hypothesis
    ): array {
        $bankName =
            $this->bankName(
                $hypothesis->statement
            );

        if ($bankName === null) {
            return [];
        }

        $accounts =
            BankAccount::query()
                ->whereRaw(
                    'LOWER(name) LIKE ?',
                    [
                        '%'.strtolower(
                            $bankName
                        ).'%',
                    ]
                )
                ->get();

        if ($accounts->isEmpty()) {
            return [
                new EvidenceItem(
                    source: 'bank_source',
                    description: sprintf(
                        'No %s bank account is currently represented in Money Imp.',
                        strtoupper(
                            $bankName
                        )
                    ),
                    position: 'missing',
                    confidence: 100,
                    metadata: [
                        'bank_name' => strtolower(
                            $bankName
                        ),
                        'account_count' => 0,
                    ]
                ),
            ];
        }

        $accountIds =
            $accounts
                ->pluck(
                    'id'
                );

        $coverage =
            BankTransaction::query()
                ->whereIn(
                    'bank_account_id',
                    $accountIds
                )
                ->selectRaw(
                    'MIN(transaction_date) as first_date, MAX(transaction_date) as last_date, COUNT(*) as transaction_count'
                )
                ->first();

        if (
            ! $coverage
            || (int) $coverage->transaction_count === 0
        ) {
            return [
                new EvidenceItem(
                    source: 'bank_source',
                    description: sprintf(
                        '%s is represented as a bank account, but no transaction history is currently available.',
                        strtoupper(
                            $bankName
                        )
                    ),
                    position: 'missing',
                    confidence: 100,
                    metadata: [
                        'bank_name' => strtolower(
                            $bankName
                        ),
                        'account_ids' => $accountIds->all(),
                    ]
                ),
            ];
        }

        $paidInvoiceAmounts =
            AccountingInvoice::query()
                ->where(
                    'client_id',
                    $hypothesis->subjectId
                )
                ->where(
                    'paid_amount',
                    '>',
                    0
                )
                ->pluck(
                    'paid_amount'
                )
                ->map(
                    fn ($amount) => round(
                        (float) $amount,
                        2
                    )
                )
                ->unique()
                ->values();

        $matchingPayments =
            BankTransaction::query()
                ->whereIn(
                    'bank_account_id',
                    $accountIds
                )
                ->where(
                    'client_id',
                    $hypothesis->subjectId
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
                ->where(
                    'amount',
                    '>',
                    0
                )
                ->get()
                ->filter(
                    fn ($transaction) => $paidInvoiceAmounts
                        ->contains(
                            round(
                                (float) $transaction->amount,
                                2
                            )
                        )
                )
                ->values();

        if ($matchingPayments->isNotEmpty()) {
            $matchedValue =
                round(
                    (float) $matchingPayments
                        ->sum(
                            'amount'
                        ),
                    2
                );

            return [
                new EvidenceItem(
                    source: 'bank_source',
                    description: sprintf(
                        '%s contains %d client-mapped payment%s matching paid invoice values, totalling %s.',
                        strtoupper(
                            $bankName
                        ),
                        $matchingPayments->count(),
                        $matchingPayments->count() === 1
                            ? ''
                            : 's',
                        $this->money(
                            $matchedValue
                        )
                    ),
                    position: 'supports',
                    confidence: 95,
                    metadata: [
                        'bank_name' => strtolower(
                            $bankName
                        ),
                        'account_ids' => $accountIds->all(),
                        'matching_payment_ids' => $matchingPayments
                            ->pluck(
                                'id'
                            )
                            ->all(),
                        'matching_payment_count' => $matchingPayments->count(),
                        'matching_payment_value' => $matchedValue,
                    ]
                ),
            ];
        }

        return [
            new EvidenceItem(
                source: 'bank_source',
                description: sprintf(
                    '%s bank evidence is represented from %s to %s across %d transactions.',
                    strtoupper(
                        $bankName
                    ),
                    $coverage->first_date,
                    $coverage->last_date,
                    $coverage->transaction_count
                ),
                position: 'neutral',
                confidence: 100,
                metadata: [
                    'bank_name' => strtolower(
                        $bankName
                    ),
                    'account_ids' => $accountIds->all(),
                    'first_date' => $coverage->first_date,
                    'last_date' => $coverage->last_date,
                    'transaction_count' => (int) $coverage->transaction_count,
                ]
            ),
        ];
    }

    private function money(
        float $value
    ): string {
        return '£'.number_format(
            abs($value),
            2
        );
    }

    private function bankName(
        string $statement
    ): ?string {
        $statement =
            strtolower(
                $statement
            );

        foreach (
            [
                'hsbc',
                'rbs',
                'natwest',
                'barclays',
                'lloyds',
                'santander',
            ] as $bank
        ) {
            if (
                str_contains(
                    $statement,
                    $bank
                )
            ) {
                return $bank;
            }
        }

        return null;
    }
}
