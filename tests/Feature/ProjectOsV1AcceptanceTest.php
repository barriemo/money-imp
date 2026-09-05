<?php

namespace Tests\Feature;

use App\Domains\Project\Decision\ProjectDecision;
use App\Domains\Project\Decision\ProjectDecisionContext;
use App\Domains\Project\Decision\ProjectDecisionContextService;
use App\Domains\Project\Decision\ProjectDecisionRequest;
use App\Domains\Project\Decision\ProjectDecisionService;
use App\Domains\Project\Decision\ProjectReviewReadinessPolicy;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\Support\ProjectOsV1AcceptanceCatalog;
use Tests\TestCase;

class ProjectOsV1AcceptanceTest extends TestCase
{
    public function test_project_os_v1_contains_exactly_eight_unique_questions(): void
    {
        $questions =
            collect(
                ProjectOsV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            8,
            $questions
        );

        $this->assertSame(
            [
                'PROJ01',
                'PROJ02',
                'PROJ03',
                'PROJ04',
                'PROJ05',
                'PROJ06',
                'PROJ07',
                'PROJ08',
            ],
            $questions
                ->pluck(
                    'id'
                )
                ->all()
        );

        $this->assertSame(
            8,
            $questions
                ->pluck(
                    'id'
                )
                ->unique()
                ->count()
        );

        $this->assertSame(
            8,
            $questions
                ->pluck(
                    'question'
                )
                ->unique()
                ->count()
        );
    }

    public function test_every_accepted_question_maps_only_to_authoritative_v1_policy(): void
    {
        $this->assertSame(
            [
                ProjectReviewReadinessPolicy::KEY,
            ],
            collect(
                ProjectOsV1AcceptanceCatalog::questions()
            )
                ->pluck(
                    'policy'
                )
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_public_project_decision_contracts(): void
    {
        foreach (
            ProjectOsV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotEmpty(
                $question[
                    'contracts'
                ]
            );

            foreach (
                $question[
                    'contracts'
                ] as $contract
            ) {
                $reflection =
                    new ReflectionClass(
                        $contract[
                            'class'
                        ]
                    );

                $this->assertTrue(
                    $reflection->hasProperty(
                        $contract[
                            'property'
                        ]
                    )
                );

                $this->assertTrue(
                    $reflection
                        ->getProperty(
                            $contract[
                                'property'
                            ]
                        )
                        ->isPublic()
                );
            }
        }
    }

    public function test_project_os_v1_accepts_recommended_conditional_and_deferred_contract_states(): void
    {
        $this->assertSame(
            [
                ProjectDecision::RECOMMENDED,
                ProjectDecision::CONDITIONAL,
                ProjectDecision::DEFERRED,
            ],
            ProjectOsV1AcceptanceCatalog::acceptedStatuses()
        );

        foreach (
            ProjectOsV1AcceptanceCatalog::acceptedStatuses() as $status
        ) {
            $this->assertContains(
                $status,
                ProjectDecision::STATUSES
            );
        }

        $policySource =
            file_get_contents(
                app_path(
                    'Domains/Project/Decision/ProjectReviewReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $policySource
        );

        $this->assertStringContainsString(
            'ProjectDecision::RECOMMENDED',
            $policySource
        );

        $this->assertStringContainsString(
            'ProjectDecision::CONDITIONAL',
            $policySource
        );

        $this->assertStringContainsString(
            'ProjectDecision::DEFERRED',
            $policySource
        );
    }

    public function test_project_os_v1_read_surface_is_registered(): void
    {
        $exit =
            Artisan::call(
                'list'
            );

        $output =
            Artisan::output();

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'project:decide-review',
            $output
        );
    }

    public function test_project_os_v1_boundary_stops_before_health_priority_ranking_action_execution_and_persistence(): void
    {
        $this->assertSame(
            [
                'Is this project healthy?',
                'Which project should be reviewed first?',
                'Rank projects by priority, risk or urgency.',
                'Create or assign a project action.',
                'Execute project work or remediation.',
                'Change project health or lifecycle status.',
                'Persist a project decision outcome.',
                'Use legacy Project Brain scoring or recommendations as authoritative truth.',
            ],
            ProjectOsV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                ProjectDecision::class,
                ProjectDecisionRequest::class,
                ProjectDecisionContext::class,
            ] as $class
        ) {
            $reflection =
                new ReflectionClass(
                    $class
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
                    'recommendationScore',
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
    }

    public function test_project_os_v1_has_one_authoritative_policy_and_one_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                ProjectReviewReadinessPolicy::class
            );

        $this->assertTrue(
            $policy->hasMethod(
                'supports'
            )
        );

        $this->assertTrue(
            $policy->hasMethod(
                'decide'
            )
        );

        $service =
            new ReflectionClass(
                ProjectDecisionService::class
            );

        $this->assertTrue(
            $service->hasMethod(
                'decide'
            )
        );

        $method =
            $service->getMethod(
                'decide'
            );

        $this->assertTrue(
            $method->isPublic()
        );

        $this->assertSame(
            ProjectDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_unsupported_project_decision_request_fails_before_context_assembly(): void
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

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            ProjectOsV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotSame(
                '',
                trim(
                    $question[
                        'question'
                    ]
                )
            );

            $this->assertNotSame(
                '',
                trim(
                    $question[
                        'answer_shape'
                    ]
                )
            );

            foreach (
                [
                    'priority',
                    'score',
                    'urgency',
                    'ranking',
                    'execution',
                    'action',
                ] as $forbidden
            ) {
                $this->assertArrayNotHasKey(
                    $forbidden,
                    $question
                );
            }

            foreach (
                $question[
                    'contracts'
                ] as $contract
            ) {
                $this->assertNotSame(
                    '',
                    trim(
                        $contract[
                            'class'
                        ]
                    )
                );

                $this->assertNotSame(
                    '',
                    trim(
                        $contract[
                            'property'
                        ]
                    )
                );
            }
        }
    }
}
