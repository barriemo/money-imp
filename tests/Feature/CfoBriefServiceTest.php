<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBrief;
use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBrief;
use App\Domains\BusinessBrain\Cfo\Briefing\CfoBriefService;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\FinancialTruth\Verification\DTOs\VerificationCandidate;
use App\Domains\FinancialTruth\Verification\Services\VerificationQueueService;
use App\Models\AccountingInvoice;
use App\Models\AccountingInvoiceItem;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CfoBriefServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cfo_brief_preserves_financial_uncertainty_and_sets_priorities(): void
    {
        $this->seedCommercialEvidence();

        $position =
            new FinancialPosition(
                cash: new CashTruth(
                    accountCount: 6,
                    verifiedAccountCount: 1,
                    freshAccountCount: 1,
                    staleAccountCount: 0,
                    unverifiedAccountCount: 5,
                    verifiedCash: 177461.02,
                    reportedAccountingBalance: 2028.28,
                    reportedUnverifiedCardDebt: 6623.36,
                    creditCardDebt: 0,
                    knownLiabilities: 0,
                    knownNetPosition: 177461.02,
                    safeAvailableCash: null,
                    ledgerReceivables: 96323.44,
                    paymentsWaitingAllocation: 8454,
                    bankVerificationConfidence: 17,
                    bankFreshnessConfidence: 17,
                    liabilityConfidence: 0,
                    receivableConfidence: 0,
                    cashConfidence: 0,
                    oldestBalanceAt: null,
                    newestBalanceAt: null
                ),

                receivables: new ReceivablesPosition(
                    ledgerOutstanding: 96323.44,
                    paymentsWaitingAllocation: 8454,
                    verifiedCollectible: null,
                    confidence: 0
                ),

                liabilities: new LiabilityPosition(
                    known: 0,
                    vat: 0,
                    paye: 0,
                    other: 0,
                    confidence: 0,
                    coverageComplete: false
                ),

                credit: new CreditTruth(
                    facilities: collect(),
                    facilityCount: 1,
                    verifiedFacilityCount: 1,
                    reportedExposure: 34351.65,
                    verifiedExposure: 34351.65,
                    reportedAvailableCredit: 0,
                    minimumPaymentsDue: 3435.16,
                    confidence: 100
                ),

                confidence: 0,

                asOf: CarbonImmutable::parse(
                    '2026-08-15 16:00:00'
                )
            );

        $brain =
            new BusinessBrainBrief(
                activeInvestigationCount: 1,
                waitingInvestigationCount: 1,
                candidateCount: 0,
                readyNowCount: 0,
                waitingForEvidenceCandidateCount: 0,
                lowerPriorityCandidateCount: 0,
                recentlyClosedCount: 0,
                experienceCount: 0,
                averageActiveConfidence: 70,
                highestConfidenceCandidate: null,
                highestImpactCandidate: null,
                bestNextCandidate: null
            );

        $this->mock(
            FinancialPositionService::class,
            function (MockInterface $mock) use ($position): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        $position
                    );
            }
        );

        $this->mock(
            VerificationQueueService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('bestNext')
                    ->once()
                    ->andReturn(
                        new VerificationCandidate(
                            key: 'bank-account-current',
                            type: 'bank_balance',
                            subject: 'Business Current Account',
                            amount: 177461.02,
                            source: 'freeagent',
                            confidence: 60,
                            priority: 90,
                            reason: 'Reported cash is not verified.',
                            recommendedAction: 'Provide current bank statement, bank balance export, or open banking evidence.'
                        )
                    );
            }
        );
        $this->mock(
            BusinessBrainBriefService::class,
            function (MockInterface $mock) use ($brain): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        $brain
                    );
            }
        );

        $brief =
            app(
                CfoBriefService::class
            )->current();

        $this->assertInstanceOf(
            CfoBrief::class,
            $brief
        );

        $this->assertSame(
            'uncertain',
            $brief->status()
        );

        $this->assertSame(
            0,
            $brief->confidence()
        );

        $this->assertContains(
            'Liability coverage is incomplete. Known liabilities must not be treated as total liabilities.',
            $brief->unknowns
        );

        $this->assertContains(
            'Collectible receivables have not yet been verified.',
            $brief->unknowns
        );

        $this->assertContains(
            'Safe available cash cannot yet be established.',
            $brief->unknowns
        );

        $this->assertContains(
            'Verify outstanding liabilities and statutory obligations.',
            $brief->priorities
        );

        $this->assertContains(
            'Do not rely on the headline financial position for a material decision until the weakest evidence gaps are resolved.',
            $brief->recommendations
        );

        $this->assertNotNull(
            $brief->bestNextVerification
        );

        $this->assertSame(
            'Business Current Account',
            $brief->bestNextVerification->subject
        );

        $this->assertSame(
            177461.02,
            $brief->bestNextVerification->amount
        );

        $this->assertNotNull(
            $brief->commercialPosition
        );

        $this->assertSame(
            75.0,
            $brief
                ->commercialPosition
                ->supportedCurrentMonthlyEquivalent
        );

        $this->assertSame(
            1,
            $brief
                ->commercialPosition
                ->currentRecurringCandidateCount
        );

        $this->assertSame(
            'invoice_history_supported_not_reconciled',
            $brief
                ->commercialPosition
                ->evidenceStatus
        );
    }

    private function seedCommercialEvidence(): void
    {
        $client = Client::factory()->create([
            'name' => 'Commercial Evidence Client',
        ]);

        foreach ([
            '2026-05-31',
            '2026-06-30',
            '2026-07-31',
        ] as $date) {
            $invoice = AccountingInvoice::create([
                'client_id' => $client->id,
                'invoice_number' => (string) str()->uuid(),
                'invoice_date' => $date,
                'status' => 'paid',
            ]);

            AccountingInvoiceItem::create([
                'accounting_invoice_id' => $invoice->id,
                'description' => 'Monthly Hosting, Security Updates & Backups',
                'quantity' => 1,
                'unit_price' => 75,
                'net_amount' => 75,
            ]);
        }
    }
}
