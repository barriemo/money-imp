<?php

namespace Tests\Feature;

use App\Domains\Project\Decision\ProjectDecisionContext;
use App\Domains\Project\Decision\ProjectDecisionContextService;
use App\Domains\Project\Decision\ProjectDecisionRequest;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class ProjectDecisionContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_requires_an_exact_positive_project_subject(): void
    {
        foreach ([0, -1] as $projectId) {
            try {
                new ProjectDecisionRequest(
                    key: 'project-review',
                    question: 'Should this exact project proceed to human project review?',
                    projectId: $projectId,
                );

                $this->fail(
                    'Invalid project id was accepted.'
                );
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'Project decision request project id must be positive.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_request_parameter_contract_is_bounded(): void
    {
        $request =
            new ProjectDecisionRequest(
                key: 'project-review',
                question: 'Should this exact project proceed to human project review?',
                projectId: 1,
                parameters: [
                    'mode' => 'review',
                    'limit' => 10,
                    'enabled' => true,
                    'optional' => null,
                ],
            );

        $this->assertSame(
            1,
            $request->projectId
        );

        $this->assertSame(
            'review',
            $request->parameters['mode']
        );
    }

    public function test_unknown_project_subject_is_rejected(): void
    {
        $request =
            $this->request(
                projectId: 999999
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Project decision subject project does not exist.'
        );

        app(
            ProjectDecisionContextService::class
        )->forDecision(
            $request,
            $this->observedAt()
        );
    }

    public function test_context_assembles_only_facts_for_the_exact_project_subject(): void
    {
        $observedAt =
            $this->observedAt();

        $project =
            Project::factory()
                ->create([
                    'name' => 'Exact Project',
                    'status' => 'active',
                    'health' => 'legacy-red',
                ]);

        $other =
            Project::factory()
                ->create([
                    'name' => 'Other Project',
                    'status' => 'active',
                    'health' => 'legacy-green',
                ]);

        $project
            ->risks()
            ->create([
                'description' => 'Critical recorded risk.',
                'severity' => 'critical',
                'status' => 'open',
            ]);

        $project
            ->risks()
            ->create([
                'description' => 'High recorded risk.',
                'severity' => 'high',
                'status' => 'open',
            ]);

        $project
            ->risks()
            ->create([
                'description' => 'Resolved critical risk.',
                'severity' => 'critical',
                'status' => 'closed',
            ]);

        $other
            ->risks()
            ->create([
                'description' => 'Other project critical risk.',
                'severity' => 'critical',
                'status' => 'open',
            ]);

        $project
            ->deliverables()
            ->create([
                'name' => 'Overdue deliverable',
                'status' => 'in_progress',
                'due_date' => $observedAt
                    ->subDays(2)
                    ->toDateString(),
            ]);

        $project
            ->deliverables()
            ->create([
                'name' => 'Completed old deliverable',
                'status' => 'complete',
                'due_date' => $observedAt
                    ->subDays(5)
                    ->toDateString(),
                'completed_at' => $observedAt
                    ->subDay(),
            ]);

        $other
            ->deliverables()
            ->create([
                'name' => 'Other overdue deliverable',
                'status' => 'in_progress',
                'due_date' => $observedAt
                    ->subDays(10)
                    ->toDateString(),
            ]);

        $olderUpdate =
            $project
                ->updates()
                ->create([
                    'submitted_by' => 'owner',
                    'summary' => 'Older update.',
                    'blockers' => 'Recorded blocker.',
                ]);

        $olderUpdate->forceFill([
            'created_at' => $observedAt
                ->subDays(5),
            'updated_at' => $observedAt
                ->subDays(5),
        ])->save();

        $latestUpdate =
            $project
                ->updates()
                ->create([
                    'submitted_by' => 'owner',
                    'summary' => 'Latest update.',
                    'risks' => 'Recorded update risk.',
                ]);

        $latestUpdate->forceFill([
            'created_at' => $observedAt
                ->subDays(2),
            'updated_at' => $observedAt
                ->subDays(2),
        ])->save();

        $other
            ->updates()
            ->create([
                'submitted_by' => 'owner',
                'summary' => 'Other project update.',
                'blockers' => 'Other blocker.',
                'risks' => 'Other risk.',
            ]);

        $project
            ->updateRequests()
            ->create([
                'reason' => 'Need current evidence.',
                'status' => 'open',
            ]);

        $project
            ->updateRequests()
            ->create([
                'reason' => 'Earlier evidence request.',
                'status' => 'responded',
                'response' => 'Provided.',
                'responded_at' => $observedAt
                    ->subDay(),
            ]);

        $other
            ->updateRequests()
            ->create([
                'reason' => 'Other project request.',
                'status' => 'open',
            ]);

        $project
            ->communications()
            ->create([
                'type' => 'email',
                'direction' => 'client',
                'summary' => 'Client communication.',
                'commitment' => 'Delivery promised.',
            ]);

        $project
            ->communications()
            ->create([
                'type' => 'note',
                'direction' => 'internal',
                'summary' => 'Internal commitment.',
                'commitment' => 'Internal promise.',
            ]);

        $other
            ->communications()
            ->create([
                'type' => 'email',
                'direction' => 'client',
                'summary' => 'Other client communication.',
                'commitment' => 'Other promise.',
            ]);

        $context =
            app(
                ProjectDecisionContextService::class
            )->forDecision(
                $this->request(
                    projectId: $project->id
                ),
                $observedAt
            );

        $this->assertSame(
            $project->id,
            $context->projectId
        );

        $this->assertSame(
            'Exact Project',
            $context->projectName
        );

        $this->assertSame(
            'active',
            $context->projectStatus
        );

        $this->assertSame(
            1,
            $context->openCriticalRiskCount
        );

        $this->assertSame(
            1,
            $context->openHighRiskCount
        );

        $this->assertSame(
            1,
            $context->overdueDeliverableCount
        );

        $this->assertSame(
            1,
            $context->updatesWithBlockersCount
        );

        $this->assertSame(
            1,
            $context->updatesWithRisksCount
        );

        $this->assertSame(
            1,
            $context->openUpdateRequestCount
        );

        $this->assertSame(
            1,
            $context->respondedUpdateRequestCount
        );

        $this->assertSame(
            1,
            $context->clientCommitmentCount
        );

        $this->assertTrue(
            $context->latestUpdateAt
                ->equalTo(
                    $observedAt->subDays(2)
                )
        );

        $this->assertTrue(
            $context->observedAt
                ->equalTo(
                    $observedAt
                )
        );
    }

    public function test_context_does_not_treat_legacy_health_as_authoritative_truth(): void
    {
        $project =
            Project::factory()
                ->create([
                    'health' => 'critical',
                ]);

        $context =
            app(
                ProjectDecisionContextService::class
            )->forDecision(
                $this->request(
                    projectId: $project->id
                ),
                $this->observedAt()
            );

        $reflection =
            new ReflectionClass(
                $context
            );

        $this->assertFalse(
            $reflection->hasProperty(
                'health'
            )
        );

        $this->assertFalse(
            $reflection->hasProperty(
                'recommendedAction'
            )
        );
    }

    public function test_context_contract_contains_no_interpretive_or_action_state(): void
    {
        $reflection =
            new ReflectionClass(
                ProjectDecisionContext::class
            );

        foreach (
            [
                'health',
                'priority',
                'score',
                'healthScore',
                'riskScore',
                'urgency',
                'ranking',
                'recommendation',
                'recommendedAction',
                'action',
                'actionId',
                'execution',
                'executedAt',
                'outcomeId',
            ] as $forbidden
        ) {
            $this->assertFalse(
                $reflection->hasProperty(
                    $forbidden
                )
            );
        }
    }

    public function test_context_service_does_not_depend_on_legacy_project_interpretation_or_action_services(): void
    {
        $source =
            file_get_contents(
                app_path(
                    'Domains/Project/Decision/ProjectDecisionContextService.php'
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
                'recommendedAction',
                'priorityProjects',
                'healthScore',
                'riskScore',
            ] as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source
            );
        }
    }

    public function test_context_assembly_is_read_only(): void
    {
        $project =
            Project::factory()
                ->create();

        $project
            ->risks()
            ->create([
                'description' => 'Recorded risk.',
                'severity' => 'high',
                'status' => 'open',
            ]);

        $before = [
            'projects' => Project::query()->count(),
            'risks' => $project->risks()->count(),
            'deliverables' => $project->deliverables()->count(),
            'updates' => $project->updates()->count(),
            'update_requests' => $project->updateRequests()->count(),
            'communications' => $project->communications()->count(),
            'actions' => $project->actions()->count(),
        ];

        app(
            ProjectDecisionContextService::class
        )->forDecision(
            $this->request(
                projectId: $project->id
            ),
            $this->observedAt()
        );

        $after = [
            'projects' => Project::query()->count(),
            'risks' => $project->risks()->count(),
            'deliverables' => $project->deliverables()->count(),
            'updates' => $project->updates()->count(),
            'update_requests' => $project->updateRequests()->count(),
            'communications' => $project->communications()->count(),
            'actions' => $project->actions()->count(),
        ];

        $this->assertSame(
            $before,
            $after
        );
    }

    public function test_context_rejects_fact_sets_attributed_to_a_different_project(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Project decision context facts must belong to the requested project.'
        );

        new ProjectDecisionContext(
            request: $this->request(
                projectId: 10
            ),

            projectId: 11,

            projectName: 'Wrong project',

            projectStatus: 'active',

            openCriticalRiskCount: 0,

            openHighRiskCount: 0,

            overdueDeliverableCount: 0,

            latestUpdateAt: null,

            updatesWithBlockersCount: 0,

            updatesWithRisksCount: 0,

            openUpdateRequestCount: 0,

            respondedUpdateRequestCount: 0,

            clientCommitmentCount: 0,

            observedAt: $this->observedAt()
        );
    }

    private function request(
        int $projectId
    ): ProjectDecisionRequest {
        return new ProjectDecisionRequest(
            key: 'project-review',

            question: 'Should this exact project proceed to human project review?',

            projectId: $projectId,
        );
    }

    private function observedAt(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            '2026-09-05 12:00:00'
        );
    }
}
