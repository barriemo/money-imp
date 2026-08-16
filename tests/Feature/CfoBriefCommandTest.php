<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\Briefing\BusinessBrainBrief;
use App\Domains\BusinessBrain\Briefing\BusinessBrainBriefService;
use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPosition;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\BusinessBrain\FinancialPosition\LiabilityPosition;
use App\Domains\BusinessBrain\FinancialPosition\ReceivablesPosition;
use App\Domains\FinancialTruth\Verification\DTOs\VerificationCandidate;
use App\Domains\FinancialTruth\Verification\Services\VerificationQueueService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class CfoBriefCommandTest extends TestCase
{
    public function test_cfo_brief_command_presents_executive_position(): void
    {
        $this->mock(
            FinancialPositionService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        new FinancialPosition(
                            cash: new CashTruth(
                                accountCount: 1,
                                verifiedAccountCount: 1,
                                freshAccountCount: 1,
                                staleAccountCount: 0,
                                unverifiedAccountCount: 0,
                                verifiedCash: 50000,
                                reportedAccountingBalance: 50000,
                                reportedUnverifiedCardDebt: 0,
                                creditCardDebt: 0,
                                knownLiabilities: 0,
                                knownNetPosition: 50000,
                                safeAvailableCash: null,
                                ledgerReceivables: 25000,
                                paymentsWaitingAllocation: 0,
                                bankVerificationConfidence: 100,
                                bankFreshnessConfidence: 100,
                                liabilityConfidence: 0,
                                receivableConfidence: 0,
                                cashConfidence: 0,
                                oldestBalanceAt: null,
                                newestBalanceAt: null
                            ),

                            receivables: new ReceivablesPosition(
                                ledgerOutstanding: 25000,
                                paymentsWaitingAllocation: 0,
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
                                facilityCount: 0,
                                verifiedFacilityCount: 0,
                                reportedExposure: 0,
                                verifiedExposure: 0,
                                reportedAvailableCredit: 0,
                                minimumPaymentsDue: 0,
                                confidence: 100
                            ),

                            confidence: 0,

                            asOf: CarbonImmutable::now()
                        )
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
                            amount: 50000,
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
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        new BusinessBrainBrief(
                            activeInvestigationCount: 0,
                            waitingInvestigationCount: 0,
                            candidateCount: 0,
                            readyNowCount: 0,
                            waitingForEvidenceCandidateCount: 0,
                            lowerPriorityCandidateCount: 0,
                            recentlyClosedCount: 0,
                            experienceCount: 0,
                            averageActiveConfidence: 0,
                            highestConfidenceCandidate: null,
                            highestImpactCandidate: null,
                            bestNextCandidate: null
                        )
                    );
            }
        );

        $exitCode =
            Artisan::call(
                'cfo:brief'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'Chief Financial Officer',
            $output
        );

        $this->assertStringContainsString(
            'UNCERTAIN',
            $output
        );

        $this->assertStringContainsString(
            'Verified cash: £50,000.00',
            $output
        );

        $this->assertStringContainsString(
            'Critical unknowns:',
            $output
        );

        $this->assertStringContainsString(
            "Today's priorities:",
            $output
        );

        $this->assertStringContainsString(
            'Best next evidence action:',
            $output
        );

        $this->assertStringContainsString(
            'Business Current Account (£50,000.00)',
            $output
        );
    }
}
