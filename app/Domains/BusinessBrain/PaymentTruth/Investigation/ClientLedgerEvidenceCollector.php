<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Investigation;

use App\Domains\BusinessBrain\Investigation\EvidenceCollector;
use App\Domains\BusinessBrain\Investigation\EvidenceItem;
use App\Domains\BusinessBrain\Investigation\Hypothesis;
use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerAnalysisService;
use App\Models\AccountingInvoice;

class ClientLedgerEvidenceCollector implements EvidenceCollector
{
    public function __construct(
        private ClientLedgerAnalysisService $ledger
    ) {}

    public function collect(
        Hypothesis $hypothesis
    ): array {
        if (
            $hypothesis->subjectType !== 'client'
        ) {
            return [];
        }

        $position =
            $this->ledger
                ->current()
                ->firstWhere(
                    'clientId',
                    $hypothesis->subjectId
                );

        if (! $position) {
            return [
                new EvidenceItem(
                    source: 'client_ledger',
                    description: 'No current client-ledger evidence could be found for this subject.',
                    position: 'missing',
                    confidence: 100
                ),
            ];
        }

        $evidence = [];

        $accountingPaid =
            round(
                (float) AccountingInvoice::query()
                    ->where(
                        'client_id',
                        $hypothesis->subjectId
                    )
                    ->where(
                        'paid_amount',
                        '>',
                        0
                    )
                    ->sum(
                        'paid_amount'
                    ),
                2
            );

        if ($accountingPaid > 0) {
            $evidence[] =
                new EvidenceItem(
                    source: 'accounting',
                    description: sprintf(
                        'Accounting records %s of client invoice value as paid.',
                        $this->money(
                            $accountingPaid
                        )
                    ),
                    position: 'supports',
                    confidence: 90,
                    metadata: [
                        'paid_value' => $accountingPaid,
                    ]
                );
        }

        if (
            $position->cashReceived
            < $accountingPaid
        ) {
            $evidence[] =
                new EvidenceItem(
                    source: 'bank',
                    description: sprintf(
                        'Canonical customer cash currently totals %s, which is below the %s accounting reports as paid.',
                        $this->money(
                            $position->cashReceived
                        ),
                        $this->money(
                            $accountingPaid
                        )
                    ),
                    position: 'neutral',
                    confidence: 95,
                    metadata: [
                        'canonical_cash' => $position->cashReceived,
                        'accounting_paid' => $accountingPaid,
                    ]
                );
        }

        if (
            $position->accountingHistoryAppearsIncomplete
        ) {
            $evidence[] =
                new EvidenceItem(
                    source: 'accounting_coverage',
                    description: sprintf(
                        'Canonical bank evidence begins on %s, while current invoice evidence begins on %s.',
                        $position->firstPaymentAt,
                        $position->firstInvoiceAt
                    ),
                    position: 'missing',
                    confidence: 100
                );
        }

        if (
            $position->bankEvidenceMayBeIncomplete
        ) {
            $evidence[] =
                new EvidenceItem(
                    source: 'bank_coverage',
                    description: 'Current canonical bank evidence may be incomplete relative to accounting-reported paid value.',
                    position: 'missing',
                    confidence: 95
                );
        }

        if (
            abs(
                $position->ledgerDifference
            ) > 1
        ) {
            $evidence[] =
                new EvidenceItem(
                    source: 'client_ledger',
                    description: sprintf(
                        'The current apparent ledger difference is %s.',
                        $this->signedMoney(
                            $position->ledgerDifference
                        )
                    ),
                    position: 'neutral',
                    confidence: 100
                );
        }

        return $evidence;
    }

    private function money(
        float $value
    ): string {
        return '£'.number_format(
            abs($value),
            2
        );
    }

    private function signedMoney(
        float $value
    ): string {
        return ($value < 0 ? '-' : '')
            .$this->money(
                $value
            );
    }
}
