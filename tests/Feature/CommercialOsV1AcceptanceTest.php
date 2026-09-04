<?php

namespace Tests\Feature;

use App\Domains\Commercial\Decision\CommercialDecision;
use App\Domains\Commercial\Decision\CommercialDecisionContext;
use App\Domains\Commercial\Decision\CommercialDecisionContextService;
use App\Domains\Commercial\Decision\CommercialDecisionRequest;
use App\Domains\Commercial\Decision\CommercialDecisionService;
use App\Domains\Commercial\Decision\ServiceReconciliationReadinessPolicy;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\Support\CommercialOsV1AcceptanceCatalog;
use Tests\TestCase;

class CommercialOsV1AcceptanceTest extends TestCase
{
    public function test_commercial_os_v1_contains_exactly_nine_unique_decision_questions(): void
    {
        $questions =
            collect(
                CommercialOsV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            9,
            $questions
        );

        $this->assertSame(
            [
                'COM01',
                'COM02',
                'COM03',
                'COM04',
                'COM05',
                'COM06',
                'COM07',
                'COM08',
                'COM09',
            ],
            $questions
                ->pluck(
                    'id'
                )
                ->all()
        );

        $this->assertSame(
            9,
            $questions
                ->pluck(
                    'id'
                )
                ->unique()
                ->count()
        );

        $this->assertSame(
            9,
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
                ServiceReconciliationReadinessPolicy::KEY,
            ],
            collect(
                CommercialOsV1AcceptanceCatalog::questions()
            )
                ->pluck(
                    'policy'
                )
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_public_commercial_decision_contracts(): void
    {
        foreach (
            CommercialOsV1AcceptanceCatalog::questions() as $question
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

    public function test_commercial_os_v1_accepts_established_guidance_and_deferral_without_claiming_conditional_policy_support(): void
    {
        $this->assertSame(
            [
                CommercialDecision::RECOMMENDED,
                CommercialDecision::DEFERRED,
            ],
            CommercialOsV1AcceptanceCatalog::acceptedStatuses()
        );

        $this->assertContains(
            CommercialDecision::CONDITIONAL,
            CommercialDecision::STATUSES
        );

        $policySource =
            file_get_contents(
                app_path(
                    'Domains/Commercial/Decision/ServiceReconciliationReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $policySource
        );

        $this->assertStringNotContainsString(
            'CommercialDecision::CONDITIONAL',
            $policySource
        );
    }

    public function test_commercial_os_v1_read_surface_is_registered(): void
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
            'commercial:decide-reconciliation',
            $output
        );
    }

    public function test_commercial_os_v1_boundary_stops_before_prioritisation_execution_canonical_mutation_and_broader_commercial_strategy(): void
    {
        $this->assertSame(
            [
                'Which commercial candidate should we reconcile first?',
                'Perform the human reconciliation for this candidate.',
                'Create or update the canonical client service from this recommendation.',
                'Send an invoice or chase this client.',
                'Which client should we upsell, retain or contact next?',
                'Should observed invoice history be treated as contracted MRR?',
            ],
            CommercialOsV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                CommercialDecision::class,
                CommercialDecisionRequest::class,
                CommercialDecisionContext::class,
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

    public function test_commercial_os_v1_has_one_authoritative_policy_and_one_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                ServiceReconciliationReadinessPolicy::class
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
                CommercialDecisionService::class
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
            CommercialDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_unsupported_commercial_decision_request_fails_explicitly(): void
    {
        $contexts =
            Mockery::mock(
                CommercialDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $policy =
            Mockery::mock(
                ServiceReconciliationReadinessPolicy::class
            );

        $policy
            ->shouldReceive(
                'supports'
            )
            ->once()
            ->andReturnFalse();

        $service =
            new CommercialDecisionService(
                $contexts,
                $policy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Commercial OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new CommercialDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported commercial question.'
            )
        );
    }

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            CommercialOsV1AcceptanceCatalog::questions() as $question
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
