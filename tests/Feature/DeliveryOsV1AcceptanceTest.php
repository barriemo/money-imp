<?php

namespace Tests\Feature;

use App\Domains\Delivery\Decision\DeliveryDecision;
use App\Domains\Delivery\Decision\DeliveryDecisionContext;
use App\Domains\Delivery\Decision\DeliveryDecisionContextService;
use App\Domains\Delivery\Decision\DeliveryDecisionRequest;
use App\Domains\Delivery\Decision\DeliveryDecisionService;
use App\Domains\Delivery\Decision\DeliveryEvidenceReviewReadinessPolicy;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\Support\DeliveryOsV1AcceptanceCatalog;
use Tests\TestCase;

class DeliveryOsV1AcceptanceTest extends TestCase
{
    public function test_delivery_os_v1_contains_exactly_seven_unique_decision_questions(): void
    {
        $questions =
            collect(
                DeliveryOsV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            7,
            $questions
        );

        $this->assertSame(
            [
                'DEL01',
                'DEL02',
                'DEL03',
                'DEL04',
                'DEL05',
                'DEL06',
                'DEL07',
            ],
            $questions
                ->pluck(
                    'id'
                )
                ->all()
        );

        $this->assertSame(
            7,
            $questions
                ->pluck(
                    'id'
                )
                ->unique()
                ->count()
        );

        $this->assertSame(
            7,
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
                DeliveryEvidenceReviewReadinessPolicy::KEY,
            ],
            collect(
                DeliveryOsV1AcceptanceCatalog::questions()
            )
                ->pluck(
                    'policy'
                )
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_public_delivery_decision_contracts(): void
    {
        foreach (
            DeliveryOsV1AcceptanceCatalog::questions() as $question
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

    public function test_delivery_os_v1_accepts_established_guidance_and_deferral_without_claiming_conditional_policy_support(): void
    {
        $this->assertSame(
            [
                DeliveryDecision::RECOMMENDED,
                DeliveryDecision::DEFERRED,
            ],
            DeliveryOsV1AcceptanceCatalog::acceptedStatuses()
        );

        $this->assertContains(
            DeliveryDecision::CONDITIONAL,
            DeliveryDecision::STATUSES
        );

        $policySource =
            file_get_contents(
                app_path(
                    'Domains/Delivery/Decision/DeliveryEvidenceReviewReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $policySource
        );

        $this->assertStringNotContainsString(
            'DeliveryDecision::CONDITIONAL',
            $policySource
        );
    }

    public function test_delivery_os_v1_read_surface_is_registered(): void
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
            'delivery:decide-evidence-review',
            $output
        );
    }

    public function test_delivery_os_v1_boundary_stops_before_prioritisation_execution_commercial_disposition_recovery_invoicing_and_health(): void
    {
        $this->assertSame(
            [
                'Which client should be reviewed first?',
                'Perform the human WorkLog review.',
                'Mark recorded work as invoice, retainer, goodwill, internal or written off.',
                'Draft or send an invoice from this delivery decision.',
                'Decide whether recorded work is commercially recoverable.',
                'Is this client delivery complete or healthy?',
                'Rank projects or delivery priorities.',
            ],
            DeliveryOsV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                DeliveryDecision::class,
                DeliveryDecisionRequest::class,
                DeliveryDecisionContext::class,
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
                    'projectId',
                    'deliverableId',
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

    public function test_delivery_os_v1_has_one_authoritative_policy_and_one_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                DeliveryEvidenceReviewReadinessPolicy::class
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
                DeliveryDecisionService::class
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
            DeliveryDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_unsupported_delivery_decision_request_fails_explicitly_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                DeliveryDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new DeliveryDecisionService(
                $contexts,
                new DeliveryEvidenceReviewReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Delivery OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new DeliveryDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported delivery question.',

                clientId: 'client-1'
            )
        );
    }

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            DeliveryOsV1AcceptanceCatalog::questions() as $question
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
