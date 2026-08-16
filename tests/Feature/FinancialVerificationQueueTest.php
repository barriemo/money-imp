<?php

namespace Tests\Feature;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use App\Domains\FinancialTruth\Verification\Services\VerificationQueueService;
use Mockery\MockInterface;
use Tests\TestCase;

class FinancialVerificationQueueTest extends TestCase
{
    public function test_unverified_bank_evidence_becomes_ranked_verification_work(): void
    {
        $this->mock(
            FinancialTruthService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('build')
                    ->once()
                    ->andReturn([
                        'accounts' => collect([
                            [
                                'id' => 'current',
                                'name' => 'Business Current Account',
                                'type' => 'StandardBankAccount',
                                'reported_balance' => 177461.02,
                                'verified' => false,
                                'confidence' => 60,
                                'source' => 'freeagent',
                            ],
                            [
                                'id' => 'amex',
                                'name' => 'Amex 1',
                                'type' => 'CreditCardAccount',
                                'reported_balance' => -4558.44,
                                'verified' => false,
                                'confidence' => 40,
                                'source' => 'freeagent',
                            ],
                            [
                                'id' => 'verified',
                                'name' => 'Verified Reserve',
                                'type' => 'StandardBankAccount',
                                'reported_balance' => 5000,
                                'verified' => true,
                                'confidence' => 100,
                                'source' => 'bank_statement',
                            ],
                        ]),
                    ]);
            }
        );

        $queue =
            app(
                VerificationQueueService::class
            )->current();

        $this->assertCount(
            2,
            $queue
        );

        $this->assertSame(
            'Business Current Account',
            $queue->first()->subject
        );

        $this->assertSame(
            177461.02,
            $queue->first()->amount
        );

        $this->assertSame(
            'bank_balance',
            $queue->first()->type
        );

        $this->assertSame(
            'Amex 1',
            $queue->last()->subject
        );
    }

    public function test_best_next_returns_highest_priority_verification_candidate(): void
    {
        $this->mock(
            FinancialTruthService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('build')
                    ->once()
                    ->andReturn([
                        'accounts' => collect([
                            [
                                'id' => 'small',
                                'name' => 'Small Reserve',
                                'type' => 'StandardBankAccount',
                                'reported_balance' => 1000,
                                'verified' => false,
                                'confidence' => 20,
                                'source' => 'freeagent',
                            ],
                            [
                                'id' => 'main',
                                'name' => 'Main Account',
                                'type' => 'StandardBankAccount',
                                'reported_balance' => 150000,
                                'verified' => false,
                                'confidence' => 60,
                                'source' => 'freeagent',
                            ],
                        ]),
                    ]);
            }
        );

        $candidate =
            app(
                VerificationQueueService::class
            )->bestNext();

        $this->assertNotNull(
            $candidate
        );

        $this->assertSame(
            'Main Account',
            $candidate->subject
        );
    }
}
