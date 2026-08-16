<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CashTruth\CashTruth;
use App\Domains\BusinessBrain\CashTruth\CashTruthService;
use App\Domains\BusinessBrain\CreditTruth\CreditTruth;
use App\Domains\BusinessBrain\CreditTruth\CreditTruthService;
use App\Domains\BusinessBrain\FinancialPosition\FinancialPositionService;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Mockery\MockInterface;
use Tests\TestCase;

class FinancialPositionServiceTest extends TestCase
{
    public function test_financial_position_does_not_treat_unknown_liabilities_as_zero_exposure(): void
    {
        $this->mock(
            CashTruthService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        new CashTruth(
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
                        )
                    );
            }
        );

        $this->mock(
            CreditTruthService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('current')
                    ->once()
                    ->andReturn(
                        new CreditTruth(
                            facilities: collect(),
                            facilityCount: 1,
                            verifiedFacilityCount: 1,
                            reportedExposure: 34351.65,
                            verifiedExposure: 34351.65,
                            reportedAvailableCredit: 0.0,
                            minimumPaymentsDue: 3435.16,
                            confidence: 100
                        )
                    );
            }
        );

        $this->mock(
            FinancialTruthService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('build')
                    ->once()
                    ->andReturn([
                        'receivables' => [
                            'ledger_outstanding' => 96323.44,
                            'payments_waiting_allocation' => 8454.0,
                            'verified_collectible' => null,
                        ],

                        'liabilities' => [
                            'total' => 0.0,
                            'vat' => 0.0,
                            'paye' => 0.0,
                            'other' => 0.0,
                        ],

                        'confidence' => [
                            'bank_balances' => 17,
                            'liabilities' => 0,
                            'receivables' => 0,
                        ],
                    ]);
            }
        );

        $position =
            app(
                FinancialPositionService::class
            )->current();

        $this->assertSame(
            177461.02,
            $position->cash->verifiedCash
        );

        $this->assertSame(
            96323.44,
            $position->receivables->ledgerOutstanding
        );

        $this->assertSame(
            0.0,
            $position->liabilities->known
        );

        $this->assertFalse(
            $position->liabilities->coverageComplete
        );

        $this->assertNull(
            $position->cash->safeAvailableCash
        );

        $this->assertSame(
            34351.65,
            $position->credit->verifiedExposure
        );

        $this->assertSame(
            3435.16,
            $position->credit->minimumPaymentsDue
        );

        $this->assertSame(
            0,
            $position->confidence
        );
    }
}
