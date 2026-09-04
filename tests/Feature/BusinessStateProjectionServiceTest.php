<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\BusinessState\BusinessState;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGap;
use App\Domains\BusinessBrain\BusinessState\BusinessStateGaps;
use App\Domains\BusinessBrain\BusinessState\BusinessStateProjectionService;
use App\Domains\BusinessBrain\BusinessState\BusinessStateService;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\BusinessBrain\RevenueTruth\CommercialGap;
use App\Domains\BusinessBrain\RevenueTruth\RevenueTruthSummary;
use Carbon\CarbonImmutable;
use Mockery;
use Tests\TestCase;

class BusinessStateProjectionServiceTest extends TestCase
{
    public function test_projection_preserves_evidence_boundaries_and_summarises_repeated_client_gaps(): void
    {
        $asOf =
            CarbonImmutable::parse(
                '2026-09-04 12:00:00'
            );

        $state =
            new BusinessState(
                financial: new FinancialPosition(
                    cash: new CashTruth(
                        accountCount: 2,

                        verifiedAccountCount: 0,

                        freshAccountCount: 0,

                        staleAccountCount: 0,

                        unverifiedAccountCount: 2,

                        verifiedCash: 0,

                        reportedAccountingBalance: 12000,

                        reportedUnverifiedCardDebt: 0,

                        creditCardDebt: 0,

                        knownLiabilities: 0,

                        knownNetPosition: 0,

                        safeAvailableCash: null,

                        ledgerReceivables: 3000,

                        paymentsWaitingAllocation: 500,

                        bankVerificationConfidence: 0,

                        bankFreshnessConfidence: 0,

                        liabilityConfidence: 0,

                        receivableConfidence: 50,

                        cashConfidence: 0,

                        oldestBalanceAt: null,

                        newestBalanceAt: null
                    ),

                    receivables: new ReceivablesPosition(
                        ledgerOutstanding: 3000,

                        paymentsWaitingAllocation: 500,

                        verifiedCollectible: null,

                        confidence: 50
                    ),

                    liabilities: new LiabilityPosition(
                        known: 0,

                        vat: 0,

                        paye: 0,

                        other: 0,

                        confidence: 0,

                        coverageComplete: false,

                        unknownCategories: [
                            'corporation_tax',
                        ]
                    ),

                    credit: new CreditTruth(
                        facilities: collect(),

                        facilityCount: 0,

                        verifiedFacilityCount: 0,

                        reportedExposure: 0,

                        verifiedExposure: 0,

                        reportedAvailableCredit: 0,

                        minimumPaymentsDue: 0,

                        confidence: 0
                    ),

                    confidence: 0,

                    asOf: $asOf
                ),

                revenue: new RevenueTruthSummary(
                    clientCount: 2,

                    grossInvoiced: 10000,

                    paidAccordingToAccounting: 7000,

                    outstanding: 3000,

                    unrecoveredWorkValue: 0,

                    bankVerifiedPaymentValue: 0,

                    clientsWithOutstandingRevenue: 2,

                    clientsWithWeakPaymentEvidence: 2,

                    clientsWithoutWorkEvidence: 2,

                    averageCommercialConfidence: 25,

                    gaps: collect([
                        new CommercialGap(
                            type: 'outstanding_revenue',

                            clientId: 'client-1',

                            client: 'Alpha',

                            title: 'Invoiced revenue remains unpaid',

                            description: '£2,000.00 remains outstanding.',

                            value: 2000,

                            priority: 90,

                            confidence: 100
                        ),

                        new CommercialGap(
                            type: 'outstanding_revenue',

                            clientId: 'client-2',

                            client: 'Beta',

                            title: 'Invoiced revenue remains unpaid',

                            description: '£1,000.00 remains outstanding.',

                            value: 1000,

                            priority: 90,

                            confidence: 100
                        ),

                        new CommercialGap(
                            type: 'missing_work_evidence',

                            clientId: 'client-1',

                            client: 'Alpha',

                            title: 'Delivery evidence is missing',

                            description: 'No work evidence.',

                            value: null,

                            priority: 70,

                            confidence: 100
                        ),

                        new CommercialGap(
                            type: 'missing_work_evidence',

                            clientId: 'client-2',

                            client: 'Beta',

                            title: 'Delivery evidence is missing',

                            description: 'No work evidence.',

                            value: null,

                            priority: 70,

                            confidence: 100
                        ),
                    ])
                ),

                clients: collect(),

                gaps: new BusinessStateGaps(
                    unknowns: collect([
                        new BusinessStateGap(
                            domain: 'cash',

                            type: 'safe_available_cash_unknown',

                            scope: 'business',

                            clientId: null,

                            client: null,

                            title: 'Safe available cash is unknown',

                            description: 'Complete current evidence is not available.'
                        ),
                    ]),

                    evidenceGaps: collect([
                        new BusinessStateGap(
                            domain: 'cash',

                            type: 'unverified_bank_balance_evidence',

                            scope: 'business',

                            clientId: null,

                            client: null,

                            title: 'Bank balance evidence is unverified',

                            description: '2 account balance record(s) are not verified.'
                        ),

                        new BusinessStateGap(
                            domain: 'client',

                            type: 'missing_work_evidence',

                            scope: 'client',

                            clientId: 'client-1',

                            client: 'Alpha',

                            title: 'No delivery evidence',

                            description: 'No work-log evidence is recorded.'
                        ),

                        new BusinessStateGap(
                            domain: 'client',

                            type: 'missing_work_evidence',

                            scope: 'client',

                            clientId: 'client-2',

                            client: 'Beta',

                            title: 'No delivery evidence',

                            description: 'No work-log evidence is recorded.'
                        ),
                    ])
                ),

                asOf: $asOf
            );

        $stateService =
            Mockery::mock(
                BusinessStateService::class
            );

        $service =
            new BusinessStateProjectionService(
                state: $stateService
            );

        $projection =
            $service->project(
                $state
            );

        $this->assertContains(
            'Verified cash established from 0 of 2 account records: £0.00.',
            $projection->financialFacts
        );

        $this->assertContains(
            'Known net position from established evidence: £0.00.',
            $projection->financialFacts
        );

        $this->assertContains(
            'Known liability exposure captured so far: £0.00.',
            $projection->financialFacts
        );

        $this->assertContains(
            'Recorded active credit facilities: 0; verified exposure: £0.00.',
            $projection->financialFacts
        );

        $this->assertFalse(
            $projection->financialFacts
                ->contains(
                    fn (string $fact) => str_starts_with(
                        $fact,
                        'Safe available cash: £'
                    )
                )
        );

        $this->assertContains(
            'Client records marked active: 2.',
            $projection->commercialFacts
        );

        $this->assertContains(
            'Work-log evidence is present for 0 of 2 active client records.',
            $projection->workFacts
        );

        $this->assertContains(
            'Unrecovered work value established from recorded work logs: £0.00.',
            $projection->workFacts
        );

        $this->assertSame(
            [
                'Outstanding invoiced revenue totals £3,000.00 across 2 client records.',
                'Largest recorded outstanding balances: Alpha £2,000.00; Beta £1,000.00.',
                '2 client records have incomplete bank-backed evidence for payments accounting marks as paid; accounting-paid revenue is £7,000.00 and approved bank-backed payment evidence is £0.00.',
            ],
            $projection
                ->commercialConditions
                ->all()
        );

        $this->assertFalse(
            $projection->commercialConditions
                ->contains(
                    fn (string $condition) => str_contains(
                        $condition,
                        'Delivery evidence is missing'
                    )
                )
        );

        $this->assertSame(
            [
                'Safe available cash is unknown — Complete current evidence is not available.',
            ],
            $projection
                ->unknowns
                ->all()
        );

        $this->assertSame(
            [
                'Bank balance evidence is unverified — 2 account balance record(s) are not verified.',
                'No delivery evidence: 2 active client records.',
            ],
            $projection
                ->evidenceGaps
                ->all()
        );

        $this->assertFalse(
            property_exists(
                $projection,
                'recommendations'
            )
        );

        $this->assertFalse(
            property_exists(
                $projection,
                'priorities'
            )
        );

        $this->assertFalse(
            property_exists(
                $projection,
                'risks'
            )
        );
    }
}
