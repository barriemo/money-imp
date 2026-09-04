<?php

namespace Tests\Feature;

use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\CommercialDecisionEvidence;
use App\Domains\Commercial\Decision\CommercialDecisionPresenter;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Commercial\Decision\CommercialDecisionService;
use App\Domains\Commercial\Decision\ServiceReconciliationReadinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class CommercialDecideReconciliationCommandTest extends TestCase
{
    public function test_command_passes_exact_three_part_subject_to_authoritative_service_and_presents_result(): void
    {
        $decision =
            new CommercialDecision(
                key: ServiceReconciliationReadinessPolicy::KEY,

                question: 'Should this exact commercial evidence set proceed to human service reconciliation now?',

                status: CommercialDecision::RECOMMENDED,

                recommendation: 'Proceed with human service reconciliation for this exact commercial evidence set.',

                rationale: 'Exact candidate is review-ready and queued.',

                evidence: collect([
                    new CommercialDecisionEvidence(
                        source: 'commercial_truth.service_reconciliation_queue',
                        description: 'The exact evidence set is present in the authoritative human service reconciliation queue.',
                        position: CommercialDecisionEvidence::SUPPORTS,
                        confidence: 100,
                        metadata: [
                            'queue_present' => true,
                        ]
                    ),
                ]),

                constraints: collect(),

                confidence: 100,

                missingTruth: collect(),

                asOf: CarbonImmutable::parse(
                    '2026-09-04 21:45:00'
                )
            );

        $this->mock(
            CommercialDecisionService::class,
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
                            CommercialDecisionRequest $request
                        ): bool => $request->key
                                === ServiceReconciliationReadinessPolicy::KEY
                            && $request->clientId
                                === 'client-1'
                            && $request->candidateFingerprint
                                === 'candidate-1'
                            && $request->evidenceFingerprint
                                === 'evidence-1'
                            && $request->parameters
                                === []
                    )
                    ->andReturn(
                        $decision
                    );
            }
        );

        $this->mock(
            CommercialDecisionPresenter::class,
            function (
                MockInterface $mock
            ) use ($decision): void {
                $mock
                    ->shouldReceive(
                        'present'
                    )
                    ->once()
                    ->withArgs(
                        fn (
                            CommercialDecision $candidate
                        ): bool => $candidate
                                === $decision
                    )
                    ->andReturn(
                        'PRESENTED COMMERCIAL DECISION'
                    );
            }
        );

        $exit =
            Artisan::call(
                'commercial:decide-reconciliation',
                [
                    'client_id' => 'client-1',

                    'candidate_fingerprint' => 'candidate-1',

                    'evidence_fingerprint' => 'evidence-1',
                ]
            );

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'PRESENTED COMMERCIAL DECISION',
            Artisan::output()
        );
    }

    public function test_command_rejects_blank_exact_subject_identity_before_service_execution(): void
    {
        $this->mock(
            CommercialDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $this->mock(
            CommercialDecisionPresenter::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'present'
                )
        );

        $exit =
            Artisan::call(
                'commercial:decide-reconciliation',
                [
                    'client_id' => '   ',

                    'candidate_fingerprint' => 'candidate-1',

                    'evidence_fingerprint' => 'evidence-1',
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
}
