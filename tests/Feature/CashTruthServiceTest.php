<?php

namespace Tests\Feature;

use App\Domains\BusinessBrain\CashTruth\CashTruthService;
use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Mockery\MockInterface;
use Tests\TestCase;

class CashTruthServiceTest extends TestCase
{
    public function test_cash_truth_distinguishes_known_position_from_safe_available_cash(): void
    {
        $this->mock(
            FinancialTruthService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive(
                        'build'
                    )
                    ->once()
                    ->andReturn([
                        'accounts' => collect([
                            [
                                'id' => 'account-1',
                                'name' => 'Current',
                                'type' => 'StandardBankAccount',
                                'balance' => 100000.0,
                                'balance_at' => now(),
                                'reported_balance' => 100000.0,
                                'reported_balance_at' => now(),
                                'verified' => true,
                                'confidence' => 100,
                                'source' => 'bank',
                            ],
                            [
                                'id' => 'account-2',
                                'name' => 'Reserve',
                                'type' => 'StandardBankAccount',
                                'balance' => 20000.0,
                                'balance_at' => now()
                                    ->subDays(30),
                                'reported_balance' => 20000.0,
                                'reported_balance_at' => now()
                                    ->subDays(30),
                                'verified' => true,
                                'confidence' => 100,
                                'source' => 'bank',
                            ],
                        ]),

                        'cash' => [
                            'available' => 120000.0,
                            'credit_card_debt' => 5000.0,
                            'known_liabilities' => 15000.0,
                            'net_position' => 100000.0,
                            'confidence' => 100,
                        ],

                        'receivables' => [
                            'ledger_outstanding' => 30000.0,
                            'payments_waiting_allocation' => 2000.0,
                            'verified_collectible' => null,
                            'confidence' => 0,
                        ],

                        'liabilities' => [
                            'total' => 15000.0,
                            'vat' => 10000.0,
                            'paye' => 5000.0,
                            'other' => 0.0,
                        ],

                        'confidence' => [
                            'bank_balances' => 100,
                            'liabilities' => 100,
                            'receivables' => 0,
                        ],
                    ]);
            }
        );

        $truth =
            app(
                CashTruthService::class
            )->current();

        $this->assertSame(
            120000.0,
            $truth->verifiedCash
        );

        $this->assertSame(
            100000.0,
            $truth->knownNetPosition
        );

        $this->assertSame(
            1,
            $truth->freshAccountCount
        );

        $this->assertSame(
            1,
            $truth->staleAccountCount
        );

        $this->assertSame(
            50,
            $truth->bankFreshnessConfidence
        );

        $this->assertSame(
            50,
            $truth->cashConfidence
        );

        $this->assertNull(
            $truth->safeAvailableCash
        );

        $this->assertSame(
            30000.0,
            $truth->ledgerReceivables
        );
    }
}
