<?php

namespace Tests\Feature;

use App\Domains\Project\Decision\ProjectDecision;
use App\Domains\Project\Decision\ProjectDecisionContext;
use App\Domains\Project\Decision\ProjectDecisionContextService;
use App\Domains\Project\Decision\ProjectDecisionRequest;
use App\Domains\Project\Decision\ProjectDecisionService;
use App\Domains\Project\Decision\ProjectReviewReadinessPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class ProjectDecisionServiceTest extends TestCase
{
    public function test_supported_request_is_contextualised_once_and_decided_by_authoritative_policy(): void
    {
        $request =
            $this->request();

        $context =
            $this->context(
                request: $request,
                openHighRiskCount: 1,
            );

        $contexts =
            Mockery::mock(
                ProjectDecisionContextService::class
            );

        $contexts
            ->shouldReceive(
                'forDecision'
            )
            ->once()
            ->with(
                $request
            )
            ->andReturn(
                $context
            );

        $service =
            new ProjectDecisionService(
                $contexts,
                new ProjectReviewReadinessPolicy
            );

        $decision =
            $service->decide(
                $request
            );

        $this->assertSame(
            ProjectDecision::RECOMMENDED,
            $decision->status
        );

        $this->assertSame(
            ProjectReviewReadinessPolicy::KEY,
            $decision->key
        );

        $this->assertSame(
            'Proceed to human project review of the recorded evidence for this exact project.',
            $decision->recommendation
        );
    }

    public function test_unsupported_request_fails_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                ProjectDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new ProjectDecisionService(
                $contexts,
                new ProjectReviewReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Project OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new ProjectDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported project question.',

                projectId: 10
            )
        );
    }

    private function request(): ProjectDecisionRequest
    {
        return new ProjectDecisionRequest(
            key: ProjectReviewReadinessPolicy::KEY,

            question: 'Should this exact project proceed to human project review?',

            projectId: 10
        );
    }

    private function context(
        ProjectDecisionRequest $request,
        int $openHighRiskCount = 0,
    ): ProjectDecisionContext {
        $observedAt =
            CarbonImmutable::parse(
                '2026-09-05 12:00:00'
            );

        return new ProjectDecisionContext(
            request: $request,

            projectId: $request->projectId,

            projectName: 'Authoritative Project',

            projectStatus: 'active',

            openCriticalRiskCount: 0,

            openHighRiskCount: $openHighRiskCount,

            overdueDeliverableCount: 0,

            latestUpdateAt: $observedAt
                ->subDay(),

            updatesWithBlockersCount: 0,

            updatesWithRisksCount: 0,

            openUpdateRequestCount: 0,

            respondedUpdateRequestCount: 0,

            clientCommitmentCount: 0,

            observedAt: $observedAt
        );
    }
}
