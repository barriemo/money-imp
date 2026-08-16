<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\PaymentTruth\Evidence\LedgerEvidenceJudge;
use App\Domains\BusinessBrain\PaymentTruth\Ledger\ClientLedgerPosition;
use Tests\TestCase;

class LedgerEvidenceJudgeTest extends TestCase
{
    public function test_inconsistent_history_is_not_treated_as_confirmed_client_debt(): void
    {
        $position =
            new ClientLedgerPosition(
                clientId: 'peak',
                clientName: 'Peak Renewables',

                firstPaymentAt: '2024-03-02',
                lastPaymentAt: '2026-07-30',

                firstInvoiceAt: '2025-10-24',
                lastInvoiceAt: '2026-06-30',

                paymentCount: 14,
                cashReceived: 1260,

                invoiceCount: 10,
                invoicedDuringPaymentWindow: 28860,

                accountingReportedPaid: 28680,
                accountingReportedOutstanding: 180,

                ledgerDifference: -27600,

                openingHistoryIncomplete: false,

                accountingHistoryAppearsIncomplete: true,

                bankEvidenceMayBeIncomplete: true
            );

        $assessment =
            app(
                LedgerEvidenceJudge::class
            )->assess(
                $position
            );

        $this->assertSame(
            'incomplete_evidence',
            $assessment->status
        );

        $this->assertSame(
            90,
            $assessment->confidence
        );

        $this->assertStringContainsString(
            'Do not treat',
            $assessment->recommendation
        );

        $this->assertNotEmpty(
            $assessment->observations
        );

        $this->assertNotEmpty(
            $assessment->possibleCauses
        );
    }

    public function test_reconciled_ledger_is_supported(): void
    {
        $position =
            new ClientLedgerPosition(
                clientId: 'client',
                clientName: 'Good Client',

                firstPaymentAt: '2026-01-10',
                lastPaymentAt: '2026-02-10',

                firstInvoiceAt: '2026-01-01',
                lastInvoiceAt: '2026-02-01',

                paymentCount: 2,
                cashReceived: 1000,

                invoiceCount: 2,
                invoicedDuringPaymentWindow: 1000,

                accountingReportedPaid: 1000,
                accountingReportedOutstanding: 0,

                ledgerDifference: 0,

                openingHistoryIncomplete: false,

                accountingHistoryAppearsIncomplete: false,

                bankEvidenceMayBeIncomplete: false
            );

        $assessment =
            app(
                LedgerEvidenceJudge::class
            )->assess(
                $position
            );

        $this->assertSame(
            'supported',
            $assessment->status
        );
    }
}
