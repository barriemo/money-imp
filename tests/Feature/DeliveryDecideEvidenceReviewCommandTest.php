<?php

namespace Tests\Feature;

use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionConstraint;
use App\Domains\Delivery\Decision\DeliveryDecisionEvidence;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecisionService;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class DeliveryDecideEvidenceReviewCommandTest extends TestCase
{
    public function test_command_passes_exact_client_subject_to_authoritative_service_and_presents_recommended_result(): void
    {
        $decision =
            $this->recommended();

        $this->mock(
            DeliveryDecisionService::class,
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
                            DeliveryDecisionRequest $request
                        ): bool => $request->key
                                === DeliveryEvidenceReviewReadinessPolicy::KEY
                            && $request->question
                                === 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?'
                            && $request->clientId
                                === 'client-1'
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
                'delivery:decide-evidence-review',
                [
                    'client_id' => 'client-1',
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
            'Delivery OS Decision',
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
            'Proceed to human review of the recorded delivery evidence for this client.',
            $output
        );

        $this->assertStringContainsString(
            'Rationale:',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTS [100%] business_brain.delivery_truth',
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
            'This surface does not prioritise clients, perform human WorkLog review, change commercial disposition, decide recoverability or invoice readiness, assess delivery health, execute or persist actions.',
            $output
        );
    }

    public function test_command_presents_deferred_missing_delivery_truth_without_inventing_recommendation(): void
    {
        $decision =
            $this->deferred();

        $this->mock(
            DeliveryDecisionService::class,
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
                'delivery:decide-evidence-review',
                [
                    'client_id' => 'client-1',
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
            'BLOCKING [100%] recorded_delivery_evidence_missing',
            $output
        );

        $this->assertStringContainsString(
            'Recorded client-attributable delivery evidence for this client is missing.',
            $output
        );
    }

    public function test_command_rejects_blank_client_identity_before_service_execution(): void
    {
        $this->mock(
            DeliveryDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $exit =
            Artisan::call(
                'delivery:decide-evidence-review',
                [
                    'client_id' => '   ',
                ]
            );

        $this->assertSame(
            1,
            $exit
        );

        $this->assertStringContainsString(
            'Client id must be a non-empty string.',
            Artisan::output()
        );
    }

    private function recommended(): DeliveryDecision
    {
        return new DeliveryDecision(
            key: DeliveryEvidenceReviewReadinessPolicy::KEY,

            question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',

            status: DeliveryDecision::RECOMMENDED,

            recommendation: 'Proceed to human review of the recorded delivery evidence for this client.',

            rationale: 'Client-attributable WorkLog-backed delivery evidence is recorded. That establishes evidence availability for human review, but it does not establish delivery completion, delivery health, recoverability, commercial disposition or invoice readiness.',

            evidence: collect([
                new DeliveryDecisionEvidence(
                    source: 'business_brain.delivery_truth',

                    description: 'Business Brain DeliveryTruth records 1 WorkLog-backed delivery evidence item(s) for this client.',

                    position: DeliveryDecisionEvidence::SUPPORTS,

                    confidence: 100,

                    metadata: [
                        'client_id' => 'client-1',

                        'work_log_count' => 1,
                    ]
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: CarbonImmutable::parse(
                '2026-09-05 09:30:00'
            )
        );
    }

    private function deferred(): DeliveryDecision
    {
        return new DeliveryDecision(
            key: DeliveryEvidenceReviewReadinessPolicy::KEY,

            question: 'Should the recorded delivery evidence for this exact client proceed to human delivery review now?',

            status: DeliveryDecision::DEFERRED,

            recommendation: null,

            rationale: 'No client-attributable WorkLog-backed delivery evidence is recorded. Delivery OS therefore cannot recommend human review of delivery evidence that is absent.',

            evidence: collect([
                new DeliveryDecisionEvidence(
                    source: 'business_brain.delivery_truth',

                    description: 'Business Brain DeliveryTruth records no WorkLog-backed delivery evidence for this client.',

                    position: DeliveryDecisionEvidence::CONTEXT,

                    confidence: 100,

                    metadata: [
                        'client_id' => 'client-1',

                        'work_log_count' => 0,
                    ]
                ),
            ]),

            constraints: collect([
                new DeliveryDecisionConstraint(
                    code: 'recorded_delivery_evidence_missing',

                    description: 'Recorded client-attributable delivery evidence is missing.',

                    type: DeliveryDecisionConstraint::BLOCKING,

                    source: 'business_brain.delivery_truth',

                    confidence: 100,

                    metadata: [
                        'client_id' => 'client-1',
                    ]
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'Recorded client-attributable delivery evidence for this client is missing.',
            ]),

            asOf: CarbonImmutable::parse(
                '2026-09-05 09:30:00'
            )
        );
    }
}
