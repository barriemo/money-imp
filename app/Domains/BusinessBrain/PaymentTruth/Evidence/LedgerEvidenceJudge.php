<?php

namespace App\Domains\BusinessBrain\PaymentTruth\Evidence;

use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerPosition;

class LedgerEvidenceJudge
{
    public function assess(
        ClientLedgerPosition $position
    ): LedgerEvidenceAssessment {
        if (
            $position->accountingHistoryAppearsIncomplete
            || $position->bankEvidenceMayBeIncomplete
        ) {
            return $this->incompleteEvidence(
                $position
            );
        }

        if (
            abs(
                $position->ledgerDifference
            ) <= 1
        ) {
            return new LedgerEvidenceAssessment(
                status: 'supported',

                confidence: 95,

                observations: [
                    sprintf(
                        'Canonical cash of %s aligns with invoice evidence of %s.',
                        $this->money(
                            $position->cashReceived
                        ),
                        $this->money(
                            $position->invoicedDuringPaymentWindow
                        )
                    ),
                ],

                possibleCauses: [],

                recommendation: 'No material client-ledger discrepancy is currently evidenced.'
            );
        }

        return new LedgerEvidenceAssessment(
            status: 'contradictory_evidence',

            confidence: 80,

            observations: [
                sprintf(
                    'Canonical cash received is %s.',
                    $this->money(
                        $position->cashReceived
                    )
                ),

                sprintf(
                    'Invoice evidence in the visible period is %s.',
                    $this->money(
                        $position->invoicedDuringPaymentWindow
                    )
                ),

                sprintf(
                    'The current ledger difference is %s.',
                    $this->signedMoney(
                        $position->ledgerDifference
                    )
                ),
            ],

            possibleCauses: [
                'Incorrect invoice payment status.',
                'Missing or misclassified bank evidence.',
                'Credits or journals not represented in the current ledger model.',
                'Incorrect client mapping.',
            ],

            recommendation: 'Investigate the conflicting evidence before treating the difference as a confirmed debtor or cash anomaly.'
        );
    }

    private function incompleteEvidence(
        ClientLedgerPosition $position
    ): LedgerEvidenceAssessment {
        $observations = [];

        if (
            $position->firstPaymentAt
            && $position->firstInvoiceAt
            && $position->firstPaymentAt
                < $position->firstInvoiceAt
        ) {
            $observations[] =
                sprintf(
                    'Canonical bank evidence begins on %s, while current invoice evidence begins on %s.',
                    $position->firstPaymentAt,
                    $position->firstInvoiceAt
                );
        }

        if (
            $position->bankEvidenceMayBeIncomplete
        ) {
            $observations[] =
                sprintf(
                    'Accounting reports %s as paid in the visible invoice population, while canonical customer cash currently totals %s.',
                    $this->money(
                        $position->accountingReportedPaid
                    ),
                    $this->money(
                        $position->cashReceived
                    )
                );
        }

        $observations[] =
            sprintf(
                'The apparent ledger difference is %s, but the evidence coverage is not sufficient to treat that as confirmed.',
                $this->signedMoney(
                    $position->ledgerDifference
                )
            );

        return new LedgerEvidenceAssessment(
            status: 'incomplete_evidence',

            confidence: 90,

            observations: $observations,

            possibleCauses: [
                'Historical accounting evidence is incomplete.',
                'Historical bank evidence is incomplete.',
                'Payments may have entered another bank account.',
                'Credits, refunds or journals may not yet be represented.',
                'Client or invoice mapping may be incomplete.',
            ],

            recommendation: 'Do not treat the apparent ledger difference as confirmed client debt. Resolve the evidence coverage gaps first.'
        );
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
