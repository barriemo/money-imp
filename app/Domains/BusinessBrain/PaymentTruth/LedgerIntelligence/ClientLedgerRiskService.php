<?php

namespace App\Domains\BusinessBrain\PaymentTruth\LedgerIntelligence;

use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerAnalysisService;
use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerPosition;
use Illuminate\Support\Collection;

class ClientLedgerRiskService
{
    public function __construct(
        private ClientLedgerAnalysisService $ledger
    ) {}

    /**
     * @return Collection<int, ClientLedgerRisk>
     */
    public function current(): Collection
    {
        return $this->ledger
            ->current()
            ->map(
                fn (ClientLedgerPosition $position) => $this->risk(
                    $position
                )
            )
            ->sortByDesc(
                'priority'
            )
            ->values();
    }

    private function risk(
        ClientLedgerPosition $position
    ): ClientLedgerRisk {
        $classification =
            $this->classification(
                $position
            );

        return new ClientLedgerRisk(
            clientId: $position->clientId,

            clientName: $position->clientName,

            classification: $classification,

            difference: $position->ledgerDifference,

            cashReceived: $position->cashReceived,

            invoiceValue: $position->invoicedDuringPaymentWindow,

            priority: $this->priority(
                $position,
                $classification
            ),

            confidence: $this->confidence(
                $position,
                $classification
            ),

            reasons: $this->reasons(
                $position,
                $classification
            ),

            actions: $this->actions(
                $classification
            )
        );
    }

    private function classification(
        ClientLedgerPosition $position
    ): string {
        /*
         * Explicitly distinguish invoice-only truth.
         *
         * Accounting-reported outstanding is evidence.
         * Absence of canonical payment evidence is also evidence.
         *
         * But absence of recognised payment does NOT prove that no
         * payment occurred somewhere in incomplete/unmapped evidence.
         */
        if (
            $position->paymentCount === 0
            && $position->accountingReportedOutstanding > 0
        ) {
            return 'invoice_balance_without_canonical_payment_evidence';
        }

        if (
            $position->paymentCount === 0
            && $position->accountingReportedPaid > 0
        ) {
            return 'accounting_paid_without_canonical_payment_evidence';
        }

        if (
            $position->paymentCount === 0
            && $position->invoiceCount > 0
        ) {
            return 'invoice_evidence_without_canonical_payment_evidence';
        }

        if (
            $position->invoiceCount === 0
            && $position->cashReceived > 0
        ) {
            return 'cash_without_invoice_evidence';
        }

        if (
            abs(
                $position->ledgerDifference
            ) <= 1
        ) {
            return 'ledger_reconciled';
        }

        if (
            $position->openingHistoryIncomplete
        ) {
            return 'historical_evidence_incomplete';
        }

        if (
            abs(
                $position->ledgerDifference
            ) >= 1000
        ) {
            return 'high_confidence_anomaly';
        }

        if (
            $position->ledgerDifference < 0
        ) {
            return 'accounting_ahead_of_bank';
        }

        return 'bank_ahead_of_accounting';
    }

    private function priority(
        ClientLedgerPosition $position,
        string $classification
    ): int {
        if (
            $classification
            === 'ledger_reconciled'
        ) {
            return 0;
        }

        $valueScore =
            min(
                60,
                (int) round(
                    $this->priorityValue(
                        $position,
                        $classification
                    ) / 500
                )
            );

        $evidenceScore =
            match ($classification) {
                'high_confidence_anomaly' => 40,

                'invoice_balance_without_canonical_payment_evidence' => 40,

                'cash_without_invoice_evidence' => 35,

                'accounting_ahead_of_bank',
                'bank_ahead_of_accounting' => 30,

                'accounting_paid_without_canonical_payment_evidence' => 20,

                'historical_evidence_incomplete' => 10,

                'invoice_evidence_without_canonical_payment_evidence' => 10,

                default => 0,
            };

        return min(
            100,
            $valueScore
            + $evidenceScore
        );
    }

    private function priorityValue(
        ClientLedgerPosition $position,
        string $classification
    ): float {
        return match ($classification) {
            'invoice_balance_without_canonical_payment_evidence' => abs(
                $position->accountingReportedOutstanding
            ),

            'accounting_paid_without_canonical_payment_evidence' => abs(
                $position->accountingReportedPaid
            ),

            default => abs(
                $position->ledgerDifference
            ),
        };
    }

    private function confidence(
        ClientLedgerPosition $position,
        string $classification
    ): int {
        return match ($classification) {
            'ledger_reconciled' => 95,

            'high_confidence_anomaly' => 90,

            'invoice_balance_without_canonical_payment_evidence' => 80,

            'cash_without_invoice_evidence' => $position->openingHistoryIncomplete
                    ? 60
                    : 90,

            'accounting_paid_without_canonical_payment_evidence' => 65,

            'invoice_evidence_without_canonical_payment_evidence' => 50,

            'historical_evidence_incomplete' => 45,

            'accounting_ahead_of_bank',
            'bank_ahead_of_accounting' => 80,

            default => 50,
        };
    }

    private function reasons(
        ClientLedgerPosition $position,
        string $classification
    ): array {
        $reasons = [
            sprintf(
                'Canonical cash received: %s.',
                $this->money(
                    $position->cashReceived
                )
            ),

            sprintf(
                'Invoice evidence in the visible period: %s.',
                $this->money(
                    $position->invoicedDuringPaymentWindow
                )
            ),

            sprintf(
                'Ledger difference: %s.',
                $this->signedMoney(
                    $position->ledgerDifference
                )
            ),
        ];

        if (
            $classification
            === 'invoice_balance_without_canonical_payment_evidence'
        ) {
            $reasons[] =
                sprintf(
                    'Accounting reports %s outstanding.',
                    $this->money(
                        $position->accountingReportedOutstanding
                    )
                );

            $reasons[] =
                'No canonical customer receipt is currently attributed to this client.';

            $reasons[] =
                'Absence of attributed canonical cash does not prove that no payment exists in unmapped or incomplete evidence.';

            $reasons[] =
                'Executive priority is based on the accounting-reported outstanding balance, not historic gross invoice value.';
        }

        if (
            $classification
            === 'accounting_paid_without_canonical_payment_evidence'
        ) {
            $reasons[] =
                sprintf(
                    'Accounting reports %s paid, but no canonical bank receipt is currently attributed to this client.',
                    $this->money(
                        $position->accountingReportedPaid
                    )
                );
        }

        if (
            $position->openingHistoryIncomplete
        ) {
            $reasons[] =
                'Historical evidence exists before the current bank evidence window.';
        }

        if (
            $classification
            === 'cash_without_invoice_evidence'
        ) {
            $reasons[] =
                'Canonical customer cash exists but no invoice evidence was found in the visible period.';
        }

        return $reasons;
    }

    private function actions(
        string $classification
    ): array {
        return match ($classification) {
            'high_confidence_anomaly' => [
                'Review the client invoice ledger.',
                'Inspect canonical bank receipts.',
                'Check client mapping, credits and journals.',
            ],

            'invoice_balance_without_canonical_payment_evidence' => [
                'Search unmatched and unmapped positive bank transactions for plausible receipts.',
                'Review payment identities and client mapping.',
                'Reconcile any supported receipts before treating the full accounting balance as verified debtor truth.',
            ],

            'accounting_paid_without_canonical_payment_evidence' => [
                'Find the bank evidence supporting the accounting-paid position.',
                'Review payment identities, mapping and migrated accounting history.',
            ],

            'invoice_evidence_without_canonical_payment_evidence' => [
                'Review whether the invoice evidence represents a live recoverable balance.',
                'Check bank and accounting evidence before concluding.',
            ],

            'cash_without_invoice_evidence' => [
                'Search for invoices under another client record.',
                'Check opening balances and migrated accounting history.',
                'Review whether the receipt is a deposit or prepayment.',
            ],

            'historical_evidence_incomplete' => [
                'Import or verify earlier bank and accounting evidence before concluding the ledger is wrong.',
            ],

            'accounting_ahead_of_bank' => [
                'Investigate invoices without corresponding bank evidence.',
            ],

            'bank_ahead_of_accounting' => [
                'Investigate receipts without sufficient invoice evidence.',
            ],

            default => [],
        };
    }

    private function money(
        float $value
    ): string {
        return '£'.number_format(
            abs(
                $value
            ),
            2
        );
    }

    private function signedMoney(
        float $value
    ): string {
        if ($value < 0) {
            return '-'.$this->money(
                $value
            );
        }

        return $this->money(
            $value
        );
    }
}
