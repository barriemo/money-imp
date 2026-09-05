<?php

namespace Tests\Feature;

use App\Domains\Billing\Decision\BillingDecision;
use App\Domains\Billing\Decision\BillingDecisionConstraint;
use App\Domains\Billing\Decision\BillingDecisionEvidence;
use App\Domains\Billing\Decision\BillingDecisionRequest;
use App\Domains\Billing\Decision\BillingDecisionService;
use App\Domains\Billing\Decision\BillingEvidenceConclusionReadinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingDecideEvidenceReadinessCommandTest extends TestCase
{
    public function test_command_passes_exact_client_service_subject_to_authoritative_service_and_presents_result(): void
    {
        $decision =
            $this->recommended();

        $this->mock(
            BillingDecisionService::class,
            function (
                MockInterface $mock
            ) use ($decision): void {
                $mock
                    ->shouldReceive(
                        'decide'
                    )
                    ->once()
                    ->withArgs(
                        fn (
                            BillingDecisionRequest $request
                        ): bool => $request->key
                                === BillingEvidenceConclusionReadinessPolicy::KEY
                            && $request->question
                                === 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?'
                            && $request->clientServiceId
                                === $this->clientServiceId()
                            && $request->parameters
                                === []
                    )
                    ->andReturn(
                        $decision
                    );
            }
        );

        $exit =
            Artisan::call(
                'billing:decide-evidence-readiness',
                [
                    'client_service_id' => $this->clientServiceId(),
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
            'Billing OS Decision',
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
            'current recurring canonical billing evidence',
            strtolower(
                $output
            )
        );

        $this->assertStringContainsString(
            'SUPPORTS [100%] canonical-observed-billing-state',
            $output
        );

        $this->assertStringContainsString(
            'Human review remains final.',
            $output
        );
    }

    public function test_command_presents_conditional_billing_conclusion_without_hiding_recurrence_uncertainty(): void
    {
        $decision =
            $this->conditional();

        $this->mock(
            BillingDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldReceive(
                    'decide'
                )
                ->once()
                ->andReturn(
                    $decision
                )
        );

        $exit =
            Artisan::call(
                'billing:decide-evidence-readiness',
                [
                    'client_service_id' => $this->clientServiceId(),
                ]
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'Status: CONDITIONAL',
            $output
        );

        $this->assertStringContainsString(
            'CONDITION recurring-billing-not-established',
            $output
        );

        $this->assertStringContainsString(
            'recurring billing is not established',
            strtolower(
                $output
            )
        );

        $this->assertStringContainsString(
            'Whether the observed billing represents recurring billing',
            $output
        );
    }

    public function test_command_rejects_invalid_client_service_uuid_before_service_execution(): void
    {
        $this->mock(
            BillingDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $exit =
            Artisan::call(
                'billing:decide-evidence-readiness',
                [
                    'client_service_id' => 'not-a-uuid',
                ]
            );

        $this->assertSame(
            1,
            $exit
        );

        $this->assertStringContainsString(
            'Client service id must be a valid UUID.',
            Artisan::output()
        );
    }

    private function recommended(): BillingDecision
    {
        return new BillingDecision(
            key: BillingEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

            status: BillingDecision::STATUS_RECOMMENDED,

            recommendation: 'Use the bounded conclusion that current recurring canonical billing evidence is established for this exact client service; the recorded current monthly equivalent is 100.00.',

            rationale: 'The current monthly equivalent describes observed billing evidence only and is not contractual billing obligation.',

            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'canonical-observed-billing-state',

                    label: 'Canonical billing evidence establishes current recurring observed billing for this exact client service.',

                    position: BillingDecisionEvidence::POSITION_SUPPORTS,

                    confidence: 100
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->observedAt()
        );
    }

    private function conditional(): BillingDecision
    {
        return new BillingDecision(
            key: BillingEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can canonical billing evidence for this exact client service support a bounded human billing-evidence conclusion now?',

            status: BillingDecision::STATUS_CONDITIONAL,

            recommendation: 'Use only the bounded conclusion that canonical billing has been observed for this exact client service; recurring billing is not established by the available canonical evidence.',

            rationale: 'Observed billing does not establish recurring billing or contractual billing obligation.',

            evidence: collect([
                new BillingDecisionEvidence(
                    key: 'canonical-observed-billing-state',

                    label: 'Canonical billing has been observed but recurring billing evidence is not established.',

                    position: BillingDecisionEvidence::POSITION_SUPPORTS,

                    confidence: 100
                ),
            ]),

            constraints: collect([
                new BillingDecisionConstraint(
                    key: 'recurring-billing-not-established',

                    label: 'Canonical billing observations exist, but recurring billing evidence is not established.',

                    type: BillingDecisionConstraint::TYPE_CONDITION
                ),
            ]),

            confidence: 100,

            missingTruth: collect([
                'Whether the observed billing represents recurring billing for this exact client service is not established.',
            ]),

            asOf: $this->observedAt()
        );
    }

    private function observedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-05 12:00:00'
        );
    }

    private function clientServiceId(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }
}
