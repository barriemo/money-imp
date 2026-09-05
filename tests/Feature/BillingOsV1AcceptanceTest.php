<?php

namespace Tests\Feature;

use App\Domains\Billing\Decision\BillingDecision;
use App\Domains\Billing\Decision\BillingDecisionContext;
use App\Domains\Billing\Decision\BillingDecisionContextService;
use App\Domains\Billing\Decision\BillingDecisionRequest;
use App\Domains\Billing\Decision\BillingDecisionService;
use App\Domains\Billing\Decision\BillingEvidenceConclusionReadinessPolicy;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;
use Tests\Support\BillingOsV1AcceptanceCatalog;
use Tests\TestCase;

class BillingOsV1AcceptanceTest extends TestCase
{
    public function test_billing_os_v1_contains_exactly_eight_unique_questions(): void
    {
        $questions =
            collect(
                BillingOsV1AcceptanceCatalog::questions()
            );

        $this->assertCount(
            8,
            $questions
        );

        $this->assertSame(
            [
                'BIL01',
                'BIL02',
                'BIL03',
                'BIL04',
                'BIL05',
                'BIL06',
                'BIL07',
                'BIL08',
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
                BillingEvidenceConclusionReadinessPolicy::KEY,
            ],
            collect(
                BillingOsV1AcceptanceCatalog::questions()
            )
                ->pluck(
                    'policy'
                )
                ->unique()
                ->values()
                ->all()
        );
    }

    public function test_every_accepted_question_is_backed_by_public_billing_decision_contracts(): void
    {
        foreach (
            BillingOsV1AcceptanceCatalog::questions() as $question
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

    public function test_billing_os_v1_accepts_recommended_and_conditional_without_claiming_deferred_policy_support(): void
    {
        $this->assertSame(
            [
                BillingDecision::STATUS_RECOMMENDED,
                BillingDecision::STATUS_CONDITIONAL,
            ],
            BillingOsV1AcceptanceCatalog::acceptedStatuses()
        );

        $this->assertSame(
            'deferred',
            BillingDecision::STATUS_DEFERRED
        );

        $policySource =
            file_get_contents(
                app_path(
                    'Domains/Billing/Decision/BillingEvidenceConclusionReadinessPolicy.php'
                )
            );

        $this->assertIsString(
            $policySource
        );

        $this->assertStringContainsString(
            'BillingDecision::STATUS_RECOMMENDED',
            $policySource
        );

        $this->assertStringContainsString(
            'BillingDecision::STATUS_CONDITIONAL',
            $policySource
        );

        $this->assertStringNotContainsString(
            'BillingDecision::STATUS_DEFERRED',
            $policySource
        );
    }

    public function test_billing_os_v1_read_surface_is_registered(): void
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
            'billing:decide-evidence-readiness',
            $output
        );
    }

    public function test_billing_os_v1_boundary_stops_before_obligation_invoice_execution_ranking_and_persistence(): void
    {
        $this->assertSame(
            [
                'What amount should we invoice this client service now?',
                'Does no canonical observed billing mean nothing is owed?',
                'Does the current monthly equivalent establish the contractual billing amount?',
                'Create a billing obligation for this client service.',
                'Draft an invoice for this client service.',
                'Send an invoice for this client service.',
                'Run bulk billing.',
                'Write this invoice to FreeAgent.',
                'Which client or service should be billed first?',
                'Rank clients or services by billing priority or urgency.',
                'Execute a billing workflow.',
                'Mutate accounting or commercial truth.',
                'Persist a Billing OS decision outcome.',
            ],
            BillingOsV1AcceptanceCatalog::boundaryQuestions()
        );

        foreach (
            [
                BillingDecision::class,
                BillingDecisionRequest::class,
                BillingDecisionContext::class,
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
                    'invoiceId',
                    'invoiceDraftId',
                    'draftInvoiceId',
                    'sendInvoice',
                    'sendInvoiceId',
                    'invoiceSendId',
                    'freeAgentInvoiceId',
                    'billingRunId',
                    'clientRank',
                    'riskScore',
                    'attentionScore',
                    'billingObligation',
                    'contractualAmount',
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

    public function test_billing_os_v1_has_one_authoritative_policy_and_one_service_surface(): void
    {
        $policy =
            new ReflectionClass(
                BillingEvidenceConclusionReadinessPolicy::class
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
                BillingDecisionService::class
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
            BillingDecision::class,
            (string) $method
                ->getReturnType()
        );
    }

    public function test_unsupported_billing_decision_request_fails_before_context_assembly(): void
    {
        $contexts =
            Mockery::mock(
                BillingDecisionContextService::class
            );

        $contexts
            ->shouldNotReceive(
                'forDecision'
            );

        $service =
            new BillingDecisionService(
                $contexts,
                new BillingEvidenceConclusionReadinessPolicy
            );

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->expectExceptionMessage(
            'Billing OS v1 has no authoritative policy for decision request unsupported.'
        );

        $service->decide(
            new BillingDecisionRequest(
                key: 'unsupported',

                question: 'Unsupported Billing question.',

                clientServiceId: '00000000-0000-4000-8000-000000000001'
            )
        );
    }

    public function test_acceptance_catalog_contains_no_priority_execution_or_empty_contract_metadata(): void
    {
        foreach (
            BillingOsV1AcceptanceCatalog::questions() as $question
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
