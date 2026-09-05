<?php

namespace Tests\Feature;

use App\Domains\Payment\Decision\PaymentDecision;
use App\Domains\Payment\Decision\PaymentDecisionConstraint;
use App\Domains\Payment\Decision\PaymentDecisionEvidence;
use App\Domains\Payment\Decision\PaymentDecisionRequest;
use App\Domains\Payment\Decision\PaymentDecisionService;
use App\Domains\Payment\Decision\PaymentEvidenceConclusionReadinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class PaymentDecideEvidenceConclusionCommandTest extends TestCase
{
    public function test_command_passes_exact_client_subject_to_authoritative_service_and_presents_result(): void
    {
        $decision =
            $this->recommended();

        $this->mock(
            PaymentDecisionService::class,
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
                            PaymentDecisionRequest $request
                        ): bool => $request->key
                                === PaymentEvidenceConclusionReadinessPolicy::KEY
                            && $request->question
                                === 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?'
                            && $request->clientId
                                === $this->clientId()
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
                'payment:decide-evidence-conclusion',
                [
                    'client_id' => $this->clientId(),
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
            'Payment OS Decision',
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
            'supports at least one payment candidate',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTS [100%] payment.evidence_search.state',
            $output
        );

        $this->assertStringContainsString(
            'Human review remains final.',
            $output
        );
    }

    public function test_command_presents_conditional_payment_conclusion_without_hiding_payer_identity_uncertainty(): void
    {
        $decision =
            $this->conditional();

        $this->mock(
            PaymentDecisionService::class,
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
                'payment:decide-evidence-conclusion',
                [
                    'client_id' => $this->clientId(),
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
            'CONDITION [100%] payer_identity_unresolved',
            $output
        );

        $this->assertStringContainsString(
            'payer identity remains unresolved',
            strtolower(
                $output
            )
        );
    }

    public function test_command_presents_deferred_result_without_inventing_payment_conclusion(): void
    {
        $decision =
            $this->deferred();

        $this->mock(
            PaymentDecisionService::class,
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
                'payment:decide-evidence-conclusion',
                [
                    'client_id' => $this->clientId(),
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
            'BLOCKING [100%] bank_evidence_missing',
            $output
        );
    }

    public function test_command_rejects_invalid_client_uuid_before_service_execution(): void
    {
        $this->mock(
            PaymentDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $exit =
            Artisan::call(
                'payment:decide-evidence-conclusion',
                [
                    'client_id' => 'not-a-uuid',
                ]
            );

        $this->assertSame(
            1,
            $exit
        );

        $this->assertStringContainsString(
            'Client id must be a valid UUID.',
            Artisan::output()
        );
    }

    private function recommended(): PaymentDecision
    {
        return new PaymentDecision(
            key: PaymentEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

            status: PaymentDecision::RECOMMENDED,

            recommendation: 'Available evidence supports at least one payment candidate for this exact client for bounded human review.',

            rationale: 'The available evidence supports a bounded candidate conclusion only.',

            evidence: collect([
                new PaymentDecisionEvidence(
                    source: 'payment.evidence_search.state',

                    description: 'One supported payment candidate is recorded.',

                    position: PaymentDecisionEvidence::SUPPORTS,

                    confidence: 100
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->observedAt()
        );
    }

    private function conditional(): PaymentDecision
    {
        return new PaymentDecision(
            key: PaymentEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

            status: PaymentDecision::CONDITIONAL,

            recommendation: 'Use only the bounded conclusion that weak unidentified exact-amount payment candidates exist while payer identity remains unresolved.',

            rationale: 'Amount coincidence exists but payer identity is not established.',

            evidence: collect([
                new PaymentDecisionEvidence(
                    source: 'payment.evidence_search.state',

                    description: 'Weak exact-amount evidence exists.',

                    position: PaymentDecisionEvidence::SUPPORTS,

                    confidence: 100
                ),
            ]),

            constraints: collect([
                new PaymentDecisionConstraint(
                    code: 'payer_identity_unresolved',

                    description: 'Payer identity remains unresolved.',

                    type: PaymentDecisionConstraint::CONDITION,

                    source: 'payment.evidence_search.weak_candidates',

                    confidence: 100
                ),
            ]),

            confidence: 100,

            missingTruth: collect([
                'Whether the weak payment candidate belongs to this exact client is not established.',
            ]),

            asOf: $this->observedAt()
        );
    }

    private function deferred(): PaymentDecision
    {
        return new PaymentDecision(
            key: PaymentEvidenceConclusionReadinessPolicy::KEY,

            question: 'Can the available payment evidence for this exact client support a bounded human payment-evidence conclusion now?',

            status: PaymentDecision::DEFERRED,

            recommendation: null,

            rationale: 'Required bank evidence is incomplete.',

            evidence: collect([
                new PaymentDecisionEvidence(
                    source: 'payment.evidence_search.context',

                    description: 'Payment evidence context was observed.',

                    position: PaymentDecisionEvidence::CONTEXT,

                    confidence: 100
                ),
            ]),

            constraints: collect([
                new PaymentDecisionConstraint(
                    code: 'bank_evidence_missing',

                    description: 'Required bank evidence is missing.',

                    type: PaymentDecisionConstraint::BLOCKING,

                    source: 'payment.evidence_search.bank',

                    confidence: 100
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'Required bank evidence is not established.',
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

    private function clientId(): string
    {
        return '00000000-0000-4000-8000-000000000001';
    }
}
