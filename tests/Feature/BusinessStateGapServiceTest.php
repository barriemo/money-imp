<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessStateGapService;
use App\Domains\BusinessBrain\BusinessState\ClientState;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\DeliveryTruth\DeliveryTruth;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\BusinessBrain\Interrogation\Coverage\BusinessTruthCoverage;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class BusinessStateGapServiceTest extends TestCase
{
    public function test_it_separates_unknown_truth_from_missing_evidence(): void
    {
        $gaps =
            (
                new BusinessStateGapService
            )->assess(
                financial: $this->financial(
                    complete: false
                ),

                clients: collect([
                    $this->clientState(
                        complete: false
                    ),
                ])
            );

        $this->assertSame(
            [
                'safe_available_cash_unknown',
                'verified_collectible_unknown',
                'liability_coverage_incomplete',
                'credit_verification_incomplete',
            ],
            $gaps->unknowns
                ->pluck(
                    'type'
                )
                ->all()
        );

        $this->assertSame(
            [
                'unverified_bank_balance_evidence',
                'stale_bank_balance_evidence',
                'missing_invoice_evidence',
                'missing_attributable_bank_evidence',
                'missing_payment_identity',
                'missing_work_evidence',
                'missing_service_evidence',
            ],
            $gaps->evidenceGaps
                ->pluck(
                    'type'
                )
                ->all()
        );

        $liabilityGap =
            $gaps->unknowns
                ->firstWhere(
                    'type',
                    'liability_coverage_incomplete'
                );

        $this->assertStringContainsString(
            'corporation_tax',
            $liabilityGap->description
        );

        $this->assertFalse(
            $gaps->evidenceGaps
                ->contains(
                    'type',
                    'missing_charlie_findings'
                )
        );
    }

    public function test_complete_truth_has_no_unknowns_or_evidence_gaps(): void
    {
        $gaps =
            (
                new BusinessStateGapService
            )->assess(
                financial: $this->financial(
                    complete: true
                ),

                clients: collect([
                    $this->clientState(
                        complete: true
                    ),
                ])
            );

        $this->assertCount(
            0,
            $gaps->unknowns
        );

        $this->assertCount(
            0,
            $gaps->evidenceGaps
        );
    }

    private function financial(
        bool $complete
    ): FinancialPosition {
        $confidence =
            $complete
                ? 100
                : 50;

        return new FinancialPosition(
            cash: new CashTruth(
                accountCount: $complete
                    ? 1
                    : 2,

                verifiedAccountCount: 1,

                freshAccountCount: $complete
                    ? 1
                    : 0,

                staleAccountCount: $complete
                    ? 0
                    : 1,

                unverifiedAccountCount: $complete
                    ? 0
                    : 1,

                verifiedCash: 10000,

                reportedAccountingBalance: 0,

                reportedUnverifiedCardDebt: 0,

                creditCardDebt: 0,

                knownLiabilities: 1000,

                knownNetPosition: 9000,

                safeAvailableCash: $complete
                    ? 9000
                    : null,

                ledgerReceivables: 2000,

                paymentsWaitingAllocation: 0,

                bankVerificationConfidence: $confidence,

                bankFreshnessConfidence: $confidence,

                liabilityConfidence: $confidence,

                receivableConfidence: $confidence,

                cashConfidence: $confidence,

                oldestBalanceAt: null,

                newestBalanceAt: null
            ),

            receivables: new ReceivablesPosition(
                ledgerOutstanding: 2000,

                paymentsWaitingAllocation: 0,

                verifiedCollectible: $complete
                    ? 2000
                    : null,

                confidence: $confidence
            ),

            liabilities: new LiabilityPosition(
                known: 1000,

                vat: 500,

                paye: 500,

                other: 0,

                confidence: $confidence,

                coverageComplete: $complete,

                unknownCategories: $complete
                    ? []
                    : [
                        'corporation_tax',
                    ]
            ),

            credit: new CreditTruth(
                facilities: collect(),

                facilityCount: $complete
                    ? 1
                    : 2,

                verifiedFacilityCount: $complete
                    ? 1
                    : 1,

                reportedExposure: 500,

                verifiedExposure: $complete
                    ? 500
                    : 250,

                reportedAvailableCredit: 1000,

                minimumPaymentsDue: 100,

                confidence: $confidence
            ),

            confidence: $confidence,

            asOf: CarbonImmutable::now()
        );
    }

    private function clientState(
        bool $complete
    ): ClientState {
        return new ClientState(
            clientId: 'client-1',

            client: 'State Client',

            delivery: new DeliveryTruth(
                clientId: 'client-1',

                client: 'State Client',

                workLogCount: $complete
                    ? 1
                    : 0,

                invoicedWorkLogCount: $complete
                    ? 1
                    : 0,

                uninvoicedWorkLogCount: 0,

                commercialValue: $complete
                    ? 1000
                    : 0,

                invoicedCommercialValue: $complete
                    ? 1000
                    : 0,

                uninvoicedCommercialValue: 0,

                invoiceLinkageConfidence: $complete
                    ? 100
                    : 0
            ),

            coverage: new BusinessTruthCoverage(
                client: 'State Client',

                invoiceCount: $complete
                    ? 1
                    : 0,

                bankTransactionCount: $complete
                    ? 1
                    : 0,

                paymentIdentityCount: $complete
                    ? 1
                    : 0,

                workLogCount: $complete
                    ? 1
                    : 0,

                serviceCount: $complete
                    ? 1
                    : 0,

                openCharlieFindingCount: 0,

                hasInvoices: $complete,

                hasBankTransactions: $complete,

                hasPaymentIdentity: $complete,

                hasWorkLogs: $complete,

                hasServices: $complete,

                /*
                 * No open Charlie finding is a valid state.
                 */
                hasCharlieFindings: false,

                confidence: $complete
                    ? 83
                    : 0
            )
        );
    }
}
