<?php

namespace Tests\Feature;

use App\Domains\Payment\Decision\PaymentDecision;
use App\Domains\Payment\Decision\PaymentDecisionContext;
use App\Domains\Payment\Decision\PaymentDecisionContextService;
use App\Domains\Payment\Decision\PaymentDecisionRequest;
use App\Domains\Payment\Decision\PaymentDecisionService;
use App\Domains\Payment\Decision\PaymentEvidenceConclusionReadinessPolicy;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\Support\PaymentOsV1AcceptanceCatalog;
use Tests\TestCase;

class PaymentOsV1AcceptanceTest extends TestCase
{
    public function test_payment_os_v1_contains_exactly_eight_unique_questions(): void
    {
        $questions =
            collect(
                PaymentOsV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            8,
            $questions
        );

        $this->assertSame(
            [
                'PAY01',
                'PAY02',
                'PAY03',
                'PAY04',
                'PAY05',
                'PAY06',
                'PAY07',
                'PAY08',
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
                PaymentEvidenceConclusionReadinessPolicy::KEY,
            ],
            collect(
                PaymentOsV1AcceptanceCatalog::questions()
            )
                ->pluck(
                    'policy'
                )
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_public_payment_decision_contracts(): void
    {
        foreach (
            PaymentOsV1AcceptanceCatalog::questions() as $question
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

    public function test_payment_os_v1_accepts_recommended_conditional_and_deferred_contract_states(): void
    {
        $this->assertSame(
            [
                PaymentDecision::RECOMMENDED,
                PaymentDecision::CONDITIONAL,
                PaymentDecision::DEFERRED,
            ],
            PaymentOsV1AcceptanceCatalog::acceptedStatuses()
        );

        foreach (
            PaymentOsV1AcceptanceCatalog::acceptedStatuses() as $status
        ) {
            $this->assertContains(
                $status,
                PaymentDecision::STATUSES
            );
        }

        $policySource =
            file_get_contents(
                app_path(
                    'Domains/Payment/Decision/PaymentEvidenceConclusionReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $policySource
        );

        $this->assertStringContainsString(
            'PaymentDecision::RECOMMENDED',
            $policySource
        );

        $this->assertStringContainsString(
            'PaymentDecision::CONDITIONAL',
            $policySource
        );

        $this->assertStringContainsString(
            'PaymentDecision::DEFERRED',
            $policySource
        );
    }

    public function test_payment_os_v1_read_surface_is_registered(): void
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
            'payment:decide-evidence-conclusion',
            $output
        );
    }

    public function test_payment_os_v1_boundary_stops_before_allocation_approval_ranking_collections_execution_and_persistence(): void
    {
        $this->assertSame(
            [
                'Did this client definitely pay?',
                'Did this client definitely not pay?',
                'Allocate this bank transaction to an invoice.',
                'Approve this payment allocation.',
                'Which client should be chased first?',
                'Rank clients by payment risk, priority or urgency.',
                'Start or execute a collections action.',
                'Draft or send an invoice or payment chase.',
                'Mutate accounting or commercial truth.',
                'Persist a Payment OS decision outcome.',
                'Use legacy client risk or attention scoring as authoritative payment truth.',
            ],
            PaymentOsV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                PaymentDecision::class,
                PaymentDecisionRequest::class,
                PaymentDecisionContext::class,
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
                    'recommendationScore',
                    'recommendedAction',
                    'allocationId',
                    'paymentAllocationId',
                    'approvalId',
                    'approvedBy',
                    'approvedAt',
                    'collectionAction',
                    'chaseAction',
                    'clientRank',
                    'riskScore',
                    'attentionScore',
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

    public function test_payment_os_v1_has_one_authoritative_policy_and_one_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                PaymentEvidenceConclusionReadinessPolicy::class
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
                PaymentDecisionService::class
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
            PaymentDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_unsupported_payment_decision_request_fails_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                PaymentDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new PaymentDecisionService(
                $contexts,
                new PaymentEvidenceConclusionReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Payment OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new PaymentDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported payment question.',

                clientId: '00000000-0000-4000-8000-000000000001'
            )
        );
    }

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            PaymentOsV1AcceptanceCatalog::questions() as $question
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
