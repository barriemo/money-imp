<?php

namespace Tests\Feature;

use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionConstraint;
use App\Domains\Cfo\Decision\CfoDecisionEvidence;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\CfoDecisionService;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class CfoDecisionCommandTest extends TestCase
{
    public function test_command_presents_established_supportable_guidance(): void
    {
        $decision =
            $this->recommended();

        $this->mock(
            CfoDecisionService::class,
            function (
                MockInterface $mock
            ) use ($decision): void {
                $mock
                    ->shouldReceive(
                        'decide'
                    )
                    ->once()
                    ->withArgs(
                        function (
                            CfoDecisionRequest $request
                        ): bool {
                            return $request->key
                                    === DiscretionarySpendDecisionPolicy::KEY
                                && $request->question
                                    === 'Can the business safely make this discretionary spend?'
                                && $request->parameters[
                                    'amount'
                                ] === 5000.0
                                && $request->parameters[
                                    'currency'
                                ] === 'GBP'
                                && $request->parameters[
                                    'recurring'
                                ] === false;
                        }
                    )
                    ->andReturn(
                        $decision
                    );
            }
        );

        $exit =
            Artisan::call(
                'cfo:decide-spend',
                [
                    'amount' => '5000',
                ]
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'MONEY IMP',
            $output
        );

        $this->assertStringContainsString(
            'CFO Decision',
            $output
        );

        $this->assertStringContainsString(
            'Status: RECOMMENDED',
            $output
        );

        $this->assertStringContainsString(
            'Recommendation confidence: 100%',
            $output
        );

        $this->assertStringContainsString(
            'financially supportable',
            $output
        );

        $this->assertStringContainsString(
            'Rationale:',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTS [100%]',
            $output
        );

        $this->assertStringContainsString(
            'Constraints:',
            $output
        );

        $this->assertStringContainsString(
            'Missing truth:',
            $output
        );

        $this->assertStringContainsString(
            'This surface does not prioritise, execute or persist actions.',
            $output
        );
    }

    public function test_command_preserves_recurring_flag_and_presents_deferred_truth(): void
    {
        $decision =
            $this->deferred();

        $this->mock(
            CfoDecisionService::class,
            function (
                MockInterface $mock
            ) use ($decision): void {
                $mock
                    ->shouldReceive(
                        'decide'
                    )
                    ->once()
                    ->withArgs(
                        function (
                            CfoDecisionRequest $request
                        ): bool {
                            return $request->parameters[
                                'amount'
                            ] === 5000.0
                                && $request->parameters[
                                    'currency'
                                ] === 'GBP'
                                && $request->parameters[
                                    'recurring'
                                ] === true;
                        }
                    )
                    ->andReturn(
                        $decision
                    );
            }
        );

        $exit =
            Artisan::call(
                'cfo:decide-spend',
                [
                    'amount' => '5000',

                    '--recurring' => true,
                ]
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'Status: DEFERRED',
            $output
        );

        $this->assertStringContainsString(
            'Recommendation confidence: 0%',
            $output
        );

        $this->assertStringContainsString(
            'Deferred — no recommendation is established.',
            $output
        );

        $this->assertStringContainsString(
            'BLOCKING [100%] forward_cash_truth_required',
            $output
        );

        $this->assertStringContainsString(
            'Forward cash availability',
            $output
        );
    }

    public function test_command_rejects_invalid_amount_before_decision_service(): void
    {
        $this->mock(
            CfoDecisionService::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        foreach (
            [
                'not-money',
                '0',
                '-1',
            ] as $amount
        ) {
            $exit =
                Artisan::call(
                    'cfo:decide-spend',
                    [
                        'amount' => $amount,
                    ]
                );

            $this->assertSame(
                1,
                $exit
            );

            $this->assertStringContainsString(
                'Spend amount must be a positive numeric GBP value.',
                Artisan::output()
            );
        }
    }

    private function recommended(): CfoDecision
    {
        return new CfoDecision(
            key: DiscretionarySpendDecisionPolicy::KEY,

            question: 'Can the business safely make this discretionary spend?',

            status: CfoDecision::RECOMMENDED,

            recommendation: 'The proposed one-off discretionary spend of £5,000.00 is financially supportable from established safe available cash.',

            rationale: 'Established safe available cash is £20,000.00. The proposed spend is £5,000.00 and would leave £15,000.00 of that established safe cash.',

            evidence: collect([
                new CfoDecisionEvidence(
                    source: 'business_state.financial.cash.safeAvailableCash',

                    description: 'Safe available cash is established at £20,000.00.',

                    position: CfoDecisionEvidence::SUPPORTS,

                    confidence: 100
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: CarbonImmutable::parse(
                '2026-09-04 18:00:00'
            )
        );
    }

    private function deferred(): CfoDecision
    {
        return new CfoDecision(
            key: DiscretionarySpendDecisionPolicy::KEY,

            question: 'Can the business safely make this discretionary spend?',

            status: CfoDecision::DEFERRED,

            recommendation: null,

            rationale: 'Current safe available cash alone cannot establish whether a recurring discretionary commitment is supportable over time.',

            evidence: collect([
                new CfoDecisionEvidence(
                    source: 'cfo_decision_request',

                    description: 'The decision request specifies a recurring discretionary spend of £5,000.00.',

                    position: CfoDecisionEvidence::CONTEXT,

                    confidence: 100
                ),
            ]),

            constraints: collect([
                new CfoDecisionConstraint(
                    code: 'forward_cash_truth_required',

                    description: 'A recurring discretionary commitment requires forward cash availability and obligation truth.',

                    type: CfoDecisionConstraint::BLOCKING,

                    source: 'cfo_policy.discretionary_spend.recurring',

                    confidence: 100
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'Forward cash availability and committed obligations across the recurring decision period are not established.',
            ]),

            asOf: CarbonImmutable::parse(
                '2026-09-04 18:00:00'
            )
        );
    }
}
