<?php

namespace Tests\Feature;

use App\Domains\Project\Decision\ProjectDecision;
use App\Domains\Project\Decision\ProjectDecisionConstraint;
use App\Domains\Project\Decision\ProjectDecisionEvidence;
use App\Domains\Project\Decision\ProjectDecisionRequest;
use App\Domains\Project\Decision\ProjectDecisionService;
use App\Domains\Project\Decision\ProjectReviewReadinessPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class ProjectDecideReviewCommandTest extends TestCase
{
    public function test_command_passes_exact_project_subject_to_authoritative_service_and_presents_result(): void
    {
        $decision =
            $this->recommended();

        $this->mock(
            ProjectDecisionService::class,
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
                            ProjectDecisionRequest $request
                        ): bool => $request->key
                                === ProjectReviewReadinessPolicy::KEY
                            && $request->question
                                === 'Should this exact project proceed to human project review?'
                            && $request->projectId
                                === 42
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
                'project:decide-review',
                [
                    'project_id' => '42',
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
            'Project OS Decision',
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
            'Proceed to human project review of the recorded evidence for this exact project.',
            $output
        );

        $this->assertStringContainsString(
            'SUPPORTS [100%] project.risks.open_high',
            $output
        );

        $this->assertStringContainsString(
            'Human review remains final.',
            $output
        );
    }

    public function test_command_presents_conditional_project_review_without_hiding_uncertainty(): void
    {
        $decision =
            $this->conditional();

        $this->mock(
            ProjectDecisionService::class,
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
                'project:decide-review',
                [
                    'project_id' => '42',
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
            'CONDITION [100%] project_update_requests_open',
            $output
        );

        $this->assertStringContainsString(
            '1 project update request(s) remain open and the requested project evidence is not yet resolved.',
            $output
        );
    }

    public function test_command_presents_deferred_result_without_inventing_recommendation(): void
    {
        $decision =
            $this->deferred();

        $this->mock(
            ProjectDecisionService::class,
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
                'project:decide-review',
                [
                    'project_id' => '42',
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
            'BLOCKING [100%] affirmative_project_review_signal_missing',
            $output
        );
    }

    public function test_command_rejects_invalid_project_identity_before_service_execution(): void
    {
        $this->mock(
            ProjectDecisionService::class,
            fn (
                MockInterface $mock
            ) => $mock
                ->shouldNotReceive(
                    'decide'
                )
        );

        $exit =
            Artisan::call(
                'project:decide-review',
                [
                    'project_id' => '0',
                ]
            );

        $this->assertSame(
            1,
            $exit
        );

        $this->assertStringContainsString(
            'Project id must be a positive integer.',
            Artisan::output()
        );
    }

    private function recommended(): ProjectDecision
    {
        return new ProjectDecision(
            key: ProjectReviewReadinessPolicy::KEY,

            question: 'Should this exact project proceed to human project review?',

            status: ProjectDecision::RECOMMENDED,

            recommendation: 'Proceed to human project review of the recorded evidence for this exact project.',

            rationale: 'An explicit recorded project review signal supports human review.',

            evidence: collect([
                new ProjectDecisionEvidence(
                    source: 'project.risks.open_high',

                    description: '1 open high project risk is recorded.',

                    position: ProjectDecisionEvidence::SUPPORTS,

                    confidence: 100,

                    metadata: [
                        'project_id' => 42,
                        'count' => 1,
                    ]
                ),
            ]),

            constraints: collect(),

            confidence: 100,

            missingTruth: collect(),

            asOf: $this->observedAt()
        );
    }

    private function conditional(): ProjectDecision
    {
        return new ProjectDecision(
            key: ProjectReviewReadinessPolicy::KEY,

            question: 'Should this exact project proceed to human project review?',

            status: ProjectDecision::CONDITIONAL,

            recommendation: 'Proceed to human project review while preserving unresolved evidence conditions.',

            rationale: 'A recorded review signal exists, but requested project evidence remains unresolved.',

            evidence: collect([
                new ProjectDecisionEvidence(
                    source: 'project.risks.open_high',

                    description: '1 open high project risk is recorded.',

                    position: ProjectDecisionEvidence::SUPPORTS,

                    confidence: 100,

                    metadata: [
                        'project_id' => 42,
                        'count' => 1,
                    ]
                ),
            ]),

            constraints: collect([
                new ProjectDecisionConstraint(
                    code: 'project_update_requests_open',

                    description: 'Requested project evidence remains unresolved.',

                    type: ProjectDecisionConstraint::CONDITION,

                    source: 'project.update_requests.open',

                    confidence: 100,

                    metadata: [
                        'project_id' => 42,
                    ]
                ),
            ]),

            confidence: 100,

            missingTruth: collect([
                '1 project update request(s) remain open and the requested project evidence is not yet resolved.',
            ]),

            asOf: $this->observedAt()
        );
    }

    private function deferred(): ProjectDecision
    {
        return new ProjectDecision(
            key: ProjectReviewReadinessPolicy::KEY,

            question: 'Should this exact project proceed to human project review?',

            status: ProjectDecision::DEFERRED,

            recommendation: null,

            rationale: 'No affirmative Project OS V1 review signal is recorded.',

            evidence: collect([
                new ProjectDecisionEvidence(
                    source: 'project.decision_context',

                    description: 'Project factual context was observed.',

                    position: ProjectDecisionEvidence::CONTEXT,

                    confidence: 100,

                    metadata: [
                        'project_id' => 42,
                    ]
                ),
            ]),

            constraints: collect([
                new ProjectDecisionConstraint(
                    code: 'affirmative_project_review_signal_missing',

                    description: 'No affirmative Project OS V1 review signal is recorded.',

                    type: ProjectDecisionConstraint::BLOCKING,

                    source: 'project.decision_context',

                    confidence: 100,

                    metadata: [
                        'project_id' => 42,
                    ]
                ),
            ]),

            confidence: 0,

            missingTruth: collect([
                'Whether this project requires human project review despite having no recorded Project OS V1 review signal is not established.',
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
}
