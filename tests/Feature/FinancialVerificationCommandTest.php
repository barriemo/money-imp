<?php

namespace Tests\Feature;

use App\Domains\FinancialTruth\Services\FinancialTruthService;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class FinancialVerificationCommandTest extends TestCase
{
    public function test_verification_command_surfaces_high_value_evidence_gap(): void
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
                        ]),
                    ]);
            }
        );

        $exitCode =
            Artisan::call(
                'money:verification'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exitCode
        );

        $this->assertStringContainsString(
            'Financial Verification Queue',
            $output
        );

        $this->assertStringContainsString(
            'Business Current Account',
            $output
        );

        $this->assertStringContainsString(
            '£177,461.02',
            $output
        );

        $this->assertStringContainsString(
            'Verification priority:',
            $output
        );
    }
}
