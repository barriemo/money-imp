<?php

namespace Tests\Feature;

use App\Domains\Executive\Decision\ExecutiveDecision;
use App\Domains\Executive\Decision\ExecutiveDecisionContext;
use App\Domains\Executive\Decision\ExecutiveDecisionContextService;
use App\Domains\Executive\Decision\ExecutiveDecisionRequest;
use App\Domains\Executive\Decision\ExecutiveDecisionService;
use App\Domains\Executive\Decision\ManagementResponseReadinessPolicy;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\Support\ExecutiveOsV1AcceptanceCatalog;
use Tests\TestCase;

class ExecutiveOsV1AcceptanceTest extends TestCase
{
    public function test_executive_os_v1_contains_exactly_one_bounded_cross_domain_decision_question(): void
    {
        $questions =
            collect(
                ExecutiveOsV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            1,
            $questions
        );

        $this->assertSame(
            ['EXE01'],
            $questions
                ->pluck('id')
                ->all()
        );
    }

    public function test_every_accepted_question_maps_only_to_authoritative_v1_policy(): void
    {
        $this->assertSame(
            [
                ManagementResponseReadinessPolicy::KEY,
            ],
            collect(
                ExecutiveOsV1AcceptanceCatalog::questions()
            )
                ->pluck('policy')
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_public_executive_decision_contracts(): void
    {
        foreach (
            ExecutiveOsV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotEmpty(
                $question['contracts']
            );

            foreach ($question['contracts'] as $contract) {
                $reflection =
                    new ReflectionClass(
                        $contract['class']
                    );

                $this->assertTrue(
                    $reflection->hasProperty(
                        $contract['property']
                    )
                );

                $this->assertTrue(
                    $reflection
                        ->getProperty(
                            $contract['property']
                        )
                        ->isPublic()
                );
            }
        }
    }

    public function test_executive_os_v1_preserves_recommended_conditional_and_deferred_contract_states(): void
    {
        $this->assertSame(
            [
                ExecutiveDecision::RECOMMENDED,
                ExecutiveDecision::CONDITIONAL,
                ExecutiveDecision::DEFERRED,
            ],
            ExecutiveOsV1AcceptanceCatalog::acceptedStatuses()
        );
    }

    public function test_executive_os_v1_read_surface_is_registered(): void
    {
        $exit =
            Artisan::call(
                'list'
            );

        $this->assertSame(
            0,
            $exit
        );

        $this->assertStringContainsString(
            'executive:decide-management-response',
            Artisan::output()
        );
    }

    public function test_executive_os_v1_boundary_stops_before_selection_ranking_merging_execution_and_persistence(): void
    {
        $this->assertSame(
            [
                'Which specialist recommendation should management prioritise first?',
                'Choose which specialist decision domains should be consulted.',
                'Merge the specialist recommendations into one new recommendation.',
                'Execute the management response.',
                'Create or persist an Executive action from this decision.',
                'Rank management actions by urgency or score.',
                'Use legacy Executive health or reasoning scores to override specialist truth.',
            ],
            ExecutiveOsV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                ExecutiveDecision::class,
                ExecutiveDecisionRequest::class,
                ExecutiveDecisionContext::class,
            ] as $class
        ) {
            $reflection =
                new ReflectionClass(
                    $class
                );

            foreach (
                [
                    'priority',
                    'score',
                    'urgency',
                    'ranking',
                    'execution',
                    'executedAt',
                    'actionId',
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

    public function test_executive_os_v1_has_one_authoritative_policy_and_one_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                ManagementResponseReadinessPolicy::class
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
                ExecutiveDecisionService::class
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
            ExecutiveDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_unsupported_executive_request_fails_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                ExecutiveDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new ExecutiveDecisionService(
                $contexts,
                new ManagementResponseReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Executive OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new ExecutiveDecisionRequest(
                key: 'unsupported',
                question: 'Unsupported Executive question.'
            )
        );
    }

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            ExecutiveOsV1AcceptanceCatalog::questions() as $question
        ) {
            $this->assertNotSame(
                '',
                trim(
                    $question['question']
                )
            );

            $this->assertNotSame(
                '',
                trim(
                    $question['answer_shape']
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

            foreach ($question['contracts'] as $contract) {
                $this->assertNotSame(
                    '',
                    trim(
                        $contract['class']
                    )
                );

                $this->assertNotSame(
                    '',
                    trim(
                        $contract['property']
                    )
                );
            }
        }
    }
}
