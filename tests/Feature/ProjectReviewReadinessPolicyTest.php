<?php

namespace Tests\Feature;

use App\Domains\Project\Decision\ProjectDecision;
use App\Domains\Project\Decision\ProjectDecisionConstraint;
use App\Domains\Project\Decision\ProjectDecisionContext;
use App\Domains\Project\Decision\ProjectDecisionEvidence;
use App\Domains\Project\Decision\ProjectDecisionRequest;
use App\Domains\Project\Decision\ProjectReviewReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class ProjectReviewReadinessPolicyTest extends TestCase
{
    public function test_policy_supports_only_the_project_review_question(): void
    {
        $policy =
            new ProjectReviewReadinessPolicy;

        $this->assertTrue(
            $policy->supports(
                $this->request()
            )
        );

        $this->assertFalse(
            $policy->supports(
                $this->request(
                    key: 'different-project-question'
                )
            )
        );
    }

    public function test_unsupported_request_fails_explicitly(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Project review readiness policy does not support decision request different-project-question.'
        );

        (new ProjectReviewReadinessPolicy)
            ->decide(
                $this->context([
                    'request' => $this->request(
                        key: 'different-project-question'
                    ),
                ])
            );
    }

    public function test_policy_rejects_parameters(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Project review readiness policy does not accept parameters.'
        );

        (new ProjectReviewReadinessPolicy)
            ->decide(
                $this->context([
                    'request' => $this->request(
                        parameters: [
                            'threshold' => 10,
                        ]
                    ),
                ])
            );
    }

    public function test_open_critical_risk_supports_human_project_review(): void
    {
        $decision =
            $this->decide([
                'openCriticalRiskCount' => 1,

                'latestUpdateAt' => $this->observedAt()
                    ->subDay(),
            ]);

        $this->assertSame(
            ProjectDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            'Proceed to human project review of the recorded evidence for this exact project.',
            $decision->recommendation
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertTrue(
            $decision
                ->evidence
                ->where(
                    'position',
                    ProjectDecisionEvidence::SUPPORTS
                )
                ->contains(
                    'source',
                    'project.risks.open_critical'
                )
        );

        $this->assertTrue(
            $decision->constraints->isEmpty()
        );

        $this->assertTrue(
            $decision->missingTruth->isEmpty()
        );
    }

    public function test_each_v1_review_signal_can_support_human_review(): void
    {
        $signals = [
            'openCriticalRiskCount' => 'project.risks.open_critical',

            'openHighRiskCount' => 'project.risks.open_high',

            'overdueDeliverableCount' => 'project.deliverables.overdue_incomplete',

            'updatesWithBlockersCount' => 'project.updates.recorded_blockers',

            'updatesWithRisksCount' => 'project.updates.recorded_risks',
        ];

        foreach ($signals as $field => $source) {
            $decision =
                $this->decide([
                    $field => 1,

                    'latestUpdateAt' => $this->observedAt()
                        ->subDay(),
                ]);

            $this->assertSame(
                ProjectDecision::RECOMMENDED,
                $decision->status,
                $field
            );

            $this->assertTrue(
                $decision
                    ->evidence
                    ->where(
                        'position',
                        ProjectDecisionEvidence::SUPPORTS
                    )
                    ->contains(
                        'source',
                        $source
                    ),
                $field
            );
        }
    }

    public function test_multiple_review_signals_are_evidence_not_a_score(): void
    {
        $decision =
            $this->decide([
                'openCriticalRiskCount' => 2,

                'overdueDeliverableCount' => 3,

                'updatesWithBlockersCount' => 4,

                'latestUpdateAt' => $this->observedAt()
                    ->subDay(),
            ]);

        $supports =
            $decision
                ->evidence
                ->where(
                    'position',
                    ProjectDecisionEvidence::SUPPORTS
                )
                ->values();

        $this->assertCount(
            3,
            $supports
        );

        $this->assertSame(
            100,
            $decision->confidence
        );

        $this->assertArrayNotHasKey(
            'score',
            $supports
                ->first()
                ->metadata
        );
    }

    public function test_review_is_conditional_when_update_requests_remain_open(): void
    {
        $decision =
            $this->decide([
                'openHighRiskCount' => 1,

                'latestUpdateAt' => $this->observedAt()
                    ->subDay(),

                'openUpdateRequestCount' => 2,
            ]);

        $this->assertSame(
            ProjectDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertNotNull(
            $decision->recommendation
        );

        $this->assertTrue(
            $decision
                ->constraints
                ->where(
                    'type',
                    ProjectDecisionConstraint::CONDITION
                )
                ->contains(
                    'code',
                    'project_update_requests_open'
                )
        );

        $this->assertCount(
            1,
            $decision->missingTruth
        );
    }

    public function test_review_is_conditional_when_project_update_is_missing(): void
    {
        $decision =
            $this->decide([
                'overdueDeliverableCount' => 1,

                'latestUpdateAt' => null,
            ]);

        $this->assertSame(
            ProjectDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertTrue(
            $decision
                ->constraints
                ->contains(
                    'code',
                    'project_update_missing'
                )
        );

        $this->assertContains(
            'No project update is recorded for this exact project.',
            $decision->missingTruth
        );
    }

    public function test_multiple_evidence_conditions_remain_explicit(): void
    {
        $decision =
            $this->decide([
                'updatesWithRisksCount' => 1,

                'latestUpdateAt' => null,

                'openUpdateRequestCount' => 2,
            ]);

        $this->assertSame(
            ProjectDecision::CONDITIONAL,
            $decision->status
        );

        $this->assertCount(
            2,
            $decision->constraints
        );

        $this->assertCount(
            2,
            $decision->missingTruth
        );
    }

    public function test_no_review_signal_defers_instead_of_claiming_project_is_healthy(): void
    {
        $decision =
            $this->decide([
                'latestUpdateAt' => $this->observedAt()
                    ->subDay(),
            ]);

        $this->assertSame(
            ProjectDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertSame(
            0,
            $decision->confidence
        );

        $this->assertTrue(
            $decision
                ->constraints
                ->where(
                    'type',
                    ProjectDecisionConstraint::BLOCKING
                )
                ->contains(
                    'code',
                    'affirmative_project_review_signal_missing'
                )
        );

        $this->assertContains(
            'Whether this project requires human project review despite having no recorded Project OS V1 review signal is not established.',
            $decision->missingTruth
        );
    }

    public function test_open_update_request_without_review_support_remains_deferred(): void
    {
        $decision =
            $this->decide([
                'latestUpdateAt' => $this->observedAt()
                    ->subDay(),

                'openUpdateRequestCount' => 1,
            ]);

        $this->assertSame(
            ProjectDecision::DEFERRED,
            $decision->status
        );

        $this->assertNull(
            $decision->recommendation
        );

        $this->assertContains(
            '1 project update request(s) remain open and the requested project evidence is not yet resolved.',
            $decision->missingTruth
        );
    }

    public function test_missing_update_without_review_support_remains_deferred(): void
    {
        $decision =
            $this->decide([
                'latestUpdateAt' => null,
            ]);

        $this->assertSame(
            ProjectDecision::DEFERRED,
            $decision->status
        );

        $this->assertContains(
            'No project update is recorded for this exact project.',
            $decision->missingTruth
        );
    }

    public function test_project_status_and_client_commitments_are_context_not_review_support(): void
    {
        $decision =
            $this->decide([
                'projectStatus' => 'active',

                'clientCommitmentCount' => 5,

                'respondedUpdateRequestCount' => 4,

                'latestUpdateAt' => $this->observedAt()
                    ->subDay(),
            ]);

        $this->assertSame(
            ProjectDecision::DEFERRED,
            $decision->status
        );

        $this->assertTrue(
            $decision
                ->evidence
                ->where(
                    'position',
                    ProjectDecisionEvidence::SUPPORTS
                )
                ->isEmpty()
        );

        $this->assertTrue(
            $decision
                ->evidence
                ->where(
                    'position',
                    ProjectDecisionEvidence::CONTEXT
                )
                ->contains(
                    'source',
                    'project.decision_context'
                )
        );
    }

    public function test_policy_source_has_no_legacy_project_or_mutation_dependency(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Project/Decision/ProjectReviewReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $source
        );

        foreach (
            [
                'ProjectHealthService',
                'ProjectBriefService',
                'ProjectPerformanceService',
                'ProjectRecommendationService',
                'ProjectActionPrioritiser',
                'ProjectActionRecommendationEngine',
                'ProjectActionService',
                'project_actions',
                'healthScore',
                'riskScore',
                'priorityProjects',
                '::query(',
                '->save(',
                '->create(',
                '->update(',
                '->delete(',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    private function decide(
        array $overrides = []
    ): ProjectDecision {
        return (new ProjectReviewReadinessPolicy)
            ->decide(
                $this->context(
                    $overrides
                )
            );
    }

    private function context(
        array $overrides = []
    ): ProjectDecisionContext {
        $values =
            array_merge(
                [
                    'request' => $this->request(),

                    'projectId' => 10,

                    'projectName' => 'Authoritative Project',

                    'projectStatus' => 'active',

                    'openCriticalRiskCount' => 0,

                    'openHighRiskCount' => 0,

                    'overdueDeliverableCount' => 0,

                    'latestUpdateAt' => $this->observedAt()
                        ->subDay(),

                    'updatesWithBlockersCount' => 0,

                    'updatesWithRisksCount' => 0,

                    'openUpdateRequestCount' => 0,

                    'respondedUpdateRequestCount' => 0,

                    'clientCommitmentCount' => 0,

                    'observedAt' => $this->observedAt(),
                ],
                $overrides
            );

        return new ProjectDecisionContext(
            request: $values['request'],

            projectId: $values['projectId'],

            projectName: $values['projectName'],

            projectStatus: $values['projectStatus'],

            openCriticalRiskCount: $values['openCriticalRiskCount'],

            openHighRiskCount: $values['openHighRiskCount'],

            overdueDeliverableCount: $values['overdueDeliverableCount'],

            latestUpdateAt: $values['latestUpdateAt'],

            updatesWithBlockersCount: $values['updatesWithBlockersCount'],

            updatesWithRisksCount: $values['updatesWithRisksCount'],

            openUpdateRequestCount: $values['openUpdateRequestCount'],

            respondedUpdateRequestCount: $values['respondedUpdateRequestCount'],

            clientCommitmentCount: $values['clientCommitmentCount'],

            observedAt: $values['observedAt']
        );
    }

    private function request(
        string $key = ProjectReviewReadinessPolicy::KEY,
        array $parameters = [],
    ): ProjectDecisionRequest {
        return new ProjectDecisionRequest(
            key: $key,

            question: 'Should this exact project proceed to human project review?',

            projectId: 10,

            parameters: $parameters,
        );
    }

    private function observedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-05 12:00:00'
        );
    }
}
