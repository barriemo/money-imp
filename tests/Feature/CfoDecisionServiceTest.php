<?php

namespace Tests\Feature;

use App\Domains\Cfo\Decision\CfoDecision;
use App\Domains\Cfo\Decision\CfoDecisionContext;
use App\Domains\Cfo\Decision\CfoDecisionContextService;
use App\Domains\Cfo\Decision\CfoDecisionRequest;
use App\Domains\Cfo\Decision\CfoDecisionService;
use App\Domains\Cfo\Decision\DiscretionarySpendDecisionPolicy;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CfoDecisionServiceTest extends TestCase
{
    public function test_supported_request_is_contextualised_once_and_decided_by_authoritative_policy(): void
    {
        $request =
            $this->request();

        $context =
            Mockery::mock(
                CfoDecisionContext::class
            );

        $decision =
            Mockery::mock(
                CfoDecision::class
            );

        $this->mock(
            DiscretionarySpendDecisionPolicy::class,
            function (
                MockInterface $mock
            ) use (
                $request,
                $context,
                $decision
            ): void {
                $mock
                    ->shouldReceive(
                        'supports'
                    )
                    ->once()
                    ->withArgs(
                        fn (CfoDecisionRequest $candidate): bool => $candidate === $request
                    )
                    ->andReturnTrue();

                $mock
                    ->shouldReceive(
                        'decide'
                    )
                    ->once()
                    ->withArgs(
                        fn (CfoDecisionContext $candidate): bool => $candidate === $context
                    )
                    ->andReturn(
                        $decision
                    );
            }
        );

        $this->mock(
            CfoDecisionContextService::class,
            function (
                MockInterface $mock
            ) use (
                $request,
                $context
            ): void {
                $mock
                    ->shouldReceive(
                        'forDecision'
                    )
                    ->once()
                    ->withArgs(
                        fn (CfoDecisionRequest $candidate): bool => $candidate === $request
                    )
                    ->andReturn(
                        $context
                    );
            }
        );

        $actual =
            app(
                CfoDecisionService::class
            )->decide(
                $request
            );

        $this->assertSame(
            $decision,
            $actual
        );
    }

    public function test_unsupported_decision_fails_before_context_is_built(): void
    {
        $request =
            new CfoDecisionRequest(
                key: 'hire_employee',

                question: 'Can we hire another employee?',

                parameters: [
                    'amount' => 5000,

                    'currency' => 'GBP',
                ]
            );

        $this->mock(
            DiscretionarySpendDecisionPolicy::class,
            function (
                MockInterface $mock
            ) use ($request): void {
                $mock
                    ->shouldReceive(
                        'supports'
                    )
                    ->once()
                    ->withArgs(
                        fn (CfoDecisionRequest $candidate): bool => $candidate === $request
                    )
                    ->andReturnFalse();

                $mock
                    ->shouldNotReceive(
                        'decide'
                    );
            }
        );

        $this->mock(
            CfoDecisionContextService::class,
            fn (MockInterface $mock) => $mock
                ->shouldNotReceive(
                    'forDecision'
                )
        );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'CFO v1 has no authoritative policy for decision request hire_employee.'
        );

        app(
            CfoDecisionService::class
        )->decide(
            $request
        );
    }

    private function request(): CfoDecisionRequest
    {
        return new CfoDecisionRequest(
            key: DiscretionarySpendDecisionPolicy::KEY,

            question: 'Can the business safely make this discretionary spend?',

            parameters: [
                'amount' => 5000,

                'currency' => 'GBP',

                'recurring' => false,
            ]
        );
    }
}
